<?php
session_start();
set_time_limit(210);
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Administrator') {
	header('Location: login.php');
	exit();
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$message = '';
$messageType = '';
$preview = $_SESSION['ai_import_preview'] ?? [];

function geminiParts(array $content): array {
	$parts = [];
	foreach ($content as $item) {
		if (($item['type'] ?? '') === 'text') {
			$parts[] = ['text' => $item['text']];
		} elseif (($item['type'] ?? '') === 'image_url' && preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', $item['image_url']['url'] ?? '', $matches)) {
			$parts[] = ['inlineData' => ['mimeType' => $matches[1], 'data' => $matches[2]]];
		}
	}
	return $parts;
}

function aiResponseText(string $response): string {
	$json = json_decode($response, true);
	if (isset($json['error']['message'])) throw new RuntimeException($json['error']['message']);
	$parts = $json['candidates'][0]['content']['parts'] ?? [];
	return implode('', array_column($parts, 'text'));
}

// Extract PDF text locally via `pdftotext` (Poppler) before sending it to Gemini.
// Install: https://github.com/oschwartz10612/poppler-windows and add its bin folder to PATH.
// Returns '' if the tool is missing or the PDF has no embedded text (e.g. a scan).
function pdfToText(string $path): string {
	$out = tempnam(sys_get_temp_dir(), 'pdftxt') . '.txt';
	@exec('pdftotext ' . escapeshellarg($path) . ' ' . escapeshellarg($out) . ' 2>&1', $lines, $code);
	if ($code !== 0 || !file_exists($out)) return '';
	$text = file_get_contents($out);
	@unlink($out);
	return trim($text);
}

function callAI(array $content): string {
	$key = getenv('GEMINI_API_KEY');
	if (!$key) throw new RuntimeException('GEMINI_API_KEY is not configured on the server.');
	$model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';
	$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
	$payload = ['contents' => [['role' => 'user', 'parts' => geminiParts($content)]], 'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json']];
	$ch = curl_init($url);
	curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 180]);
	$response = curl_exec($ch); $error = curl_error($ch); $errorCode = curl_errno($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
	if ($response === false || $error) {
		if ($errorCode === CURLE_OPERATION_TIMEDOUT) throw new RuntimeException('Gemini took too long to analyse this document. Try a smaller file or a clearer crop of the timetable.');
		throw new RuntimeException('Gemini request failed: ' . ($error ?: 'no response'));
	}
	if ($status < 200 || $status >= 300) throw new RuntimeException('Gemini request failed: ' . (json_decode($response, true)['error']['message'] ?? "HTTP $status"));
	$text = aiResponseText($response);
	if (trim($text) === '') throw new RuntimeException('Gemini returned an empty response.');
	return $text;
}

function importRows(string $text): array {
	$text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));
	$data = json_decode($text, true);
	if (!is_array($data)) {
		$start = strpos($text, '{'); $end = strrpos($text, '}');
		if ($start !== false && $end > $start) $data = json_decode(substr($text, $start, $end - $start + 1), true);
	}
	if (isset($data['rows']) && is_array($data['rows'])) return $data['rows'];
	if (is_array($data) && array_is_list($data)) return $data;
	throw new RuntimeException('The model returned no usable timetable rows.');
}

function valueOf(array $row, array $keys, $fallback = '') {
	foreach ($keys as $key) if (isset($row[$key]) && $row[$key] !== '') return $row[$key];
	return $fallback;
}

try {
	$programmes = $pdo->query('SELECT id, program_code, program_name FROM programmes ORDER BY program_code')->fetchAll();
	$courses = $pdo->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code')->fetchAll();
	$rooms = $pdo->query('SELECT id, room_name FROM rooms ORDER BY room_name')->fetchAll();

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) throw new RuntimeException('Session expired. Refresh and try again.');

		if (isset($_POST['analyse_document'])) {
			if ($_FILES['timetable_document']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Choose a document first.');
			$file = $_FILES['timetable_document'];
			if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('The document must be 10 MB or smaller.');
			$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
			if (!in_array($mime, ['application/pdf', 'text/csv', 'text/plain', 'image/png', 'image/jpeg'], true)) throw new RuntimeException('Use PDF, CSV, TXT, PNG, or JPG.');
			$prompt = 'Extract every timetable row. Return JSON only as {"rows":[{"program_code":"","course_code":"","course_name":"","year_level":1,"semester":1,"day_of_week":"Monday","start_time":"08:00","end_time":"09:00","room_name":"","type":"Class"}]}. Decode values like 4,2 as year 4 semester 2. Apply merged time headers to following rows. Use Not assigned for unknown values. Do not explain.';

			if ($mime === 'application/pdf') {
				$text = pdfToText($file['tmp_name']);
				if ($text === '') throw new RuntimeException('Could not extract text from this PDF (it may be a scanned image). Install poppler\'s pdftotext, or export/upload the timetable as PNG/JPG instead.');
				$content = [['type' => 'text', 'text' => $prompt . "\n\nDocument content:\n" . $text]];
			} elseif (str_starts_with($mime, 'image/')) {
				$content = [
					['type' => 'text', 'text' => $prompt],
					['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file['tmp_name']))]],
				];
			} else {
				$content = [['type' => 'text', 'text' => $prompt . "\n\nDocument content:\n" . file_get_contents($file['tmp_name'])]];
			}

			$aiText = callAI($content);
			$preview = [];
			foreach (importRows($aiText) as $row) {
				$preview[] = [
					'program_code' => trim((string) valueOf($row, ['program_code', 'programme_code', 'program'], 'Not assigned')),
					'course_code' => trim((string) valueOf($row, ['course_code', 'subject_code', 'course'], 'Not assigned')),
					'course_name' => trim((string) valueOf($row, ['course_name', 'subject_name', 'title'], 'Not assigned')),
					'year_level' => max(1, min(4, (int) valueOf($row, ['year_level', 'year', 'level'], 1))),
					'semester' => max(1, min(2, (int) valueOf($row, ['semester', 'term'], 1))),
					'day_of_week' => ucfirst(strtolower((string) valueOf($row, ['day_of_week', 'day'], 'Monday'))),
					'start_time' => (string) valueOf($row, ['start_time', 'start'], '08:00'),
					'end_time' => (string) valueOf($row, ['end_time', 'end'], '09:00'),
					'room_name' => trim((string) valueOf($row, ['room_name', 'room', 'venue'], 'Not assigned')),
					'type' => in_array(valueOf($row, ['type'], 'Class'), ['CA', 'Exam'], true) ? valueOf($row, ['type']) : 'Class'
				];
			}
			$_SESSION['ai_import_preview'] = $preview;
			$message = count($preview) . ' rows extracted. Review and confirm the import.'; $messageType = 'success';
		}

		if (isset($_POST['clear_preview'])) { unset($_SESSION['ai_import_preview']); $preview = []; $message = 'Preview cleared.'; $messageType = 'success'; }

		if (isset($_POST['confirm_import'])) {
			$preview = $_SESSION['ai_import_preview'] ?? [];
			if (!$preview) throw new RuntimeException('No preview is ready to import.');
			$pdo->beginTransaction();
			foreach ($preview as $row) {
				$find = $pdo->prepare('SELECT id FROM programmes WHERE program_code = :code LIMIT 1'); $find->execute([':code' => $row['program_code']]); $program = $find->fetch();
				if (!$program) { $add = $pdo->prepare('INSERT INTO programmes (program_code, program_name, level) VALUES (:code, :name, :level)'); $add->execute([':code' => $row['program_code'], ':name' => $row['program_code'], ':level' => $row['year_level']]); $programId = $pdo->lastInsertId(); } else $programId = $program['id'];
				$find = $pdo->prepare('SELECT id FROM courses WHERE course_code = :code LIMIT 1'); $find->execute([':code' => $row['course_code']]); $course = $find->fetch();
				if (!$course) { $add = $pdo->prepare('INSERT INTO courses (course_code, course_name) VALUES (:code, :name)'); $add->execute([':code' => $row['course_code'], ':name' => $row['course_name']]); $courseId = $pdo->lastInsertId(); } else $courseId = $course['id'];
				$find = $pdo->prepare('SELECT id FROM rooms WHERE room_name = :name LIMIT 1'); $find->execute([':name' => $row['room_name']]); $room = $find->fetch();
				if (!$room) { $add = $pdo->prepare('INSERT INTO rooms (room_name, capacity) VALUES (:name, 50)'); $add->execute([':name' => $row['room_name']]); $roomId = $pdo->lastInsertId(); } else $roomId = $room['id'];
				$map = $pdo->prepare('INSERT IGNORE INTO course_program (course_id, program_id, year_level, semester) VALUES (:course, :program, :year, :semester)'); $map->execute([':course' => $courseId, ':program' => $programId, ':year' => $row['year_level'], ':semester' => $row['semester']]);
				$add = $pdo->prepare('INSERT INTO schedules (program_id, course_id, room_id, type, day_of_week, start_time, end_time, year_level, semester) VALUES (:program, :course, :room, :type, :day, :start, :end, :year, :semester)'); $add->execute([':program' => $programId, ':course' => $courseId, ':room' => $roomId, ':type' => $row['type'], ':day' => $row['day_of_week'], ':start' => $row['start_time'], ':end' => $row['end_time'], ':year' => $row['year_level'], ':semester' => $row['semester']]);
			}
			$pdo->commit(); unset($_SESSION['ai_import_preview']); $preview = []; $message = 'Timetable imported successfully.'; $messageType = 'success';
		}
	}
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $message = $e->getMessage(); $messageType = 'error'; }
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>AI Timetable Import</title><link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg"><link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>"></head>
<body class="dashboard-body"><nav class="navbar"><div class="logo">ReSchedule<span>.</span></div><div class="user-profile"><a href="dashboard.php" class="card-link">&larr; Dashboard</a><a href="logout.php" class="btn-logout">Log Out</a></div></nav>
<main class="dashboard-container"><header class="welcome-banner"><div><span class="eyebrow">Administrator workspace</span><h1>AI timetable import</h1><p>Upload, review, and confirm a timetable document.</p></div></header>
<?php if ($message): ?><div class="alert-box <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<section class="dash-card"><h3>Analyse document</h3><form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><input type="file" name="timetable_document" accept=".pdf,.csv,.txt,.png,.jpg,.jpeg" required><button class="btn-submit" name="analyse_document">Analyse timetable</button></form></section>
<section class="dash-card"><h3>CSV template</h3><p>Download a CSV template for bulk timetable updates.</p><a class="filter-export" href="data:text/csv;charset=utf-8,Program%20Code,Course%20Code,Course%20Name,Year%20Level,Semester,Day,Start%20Time,End%20Time,Room%20Name,Type%0AABC,CS101,Programming%20Fundamentals,1,1,Monday,08:00,09:00,LT1,Class" download="timetable_template.csv">Download template</a></section>
<?php if ($preview): ?><section class="dash-card"><h3>Import preview</h3><div style="overflow-x:auto"><table class="schedule-table"><thead><tr><th>Programme</th><th>Course</th><th>Year</th><th>Semester</th><th>Day</th><th>Time</th><th>Room</th></tr></thead><tbody><?php foreach ($preview as $row): ?><tr><td><?php echo htmlspecialchars($row['program_code']); ?></td><td><?php echo htmlspecialchars($row['course_code'] . ' - ' . $row['course_name']); ?></td><td><?php echo $row['year_level']; ?></td><td><?php echo $row['semester']; ?></td><td><?php echo htmlspecialchars($row['day_of_week']); ?></td><td><?php echo htmlspecialchars($row['start_time'] . ' - ' . $row['end_time']); ?></td><td><?php echo htmlspecialchars($row['room_name']); ?></td></tr><?php endforeach; ?></tbody></table></div><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><button class="btn-submit" name="confirm_import">Confirm import</button><button class="filter-reset" name="clear_preview">Clear</button></form></section><?php endif; ?></main></body></html>
