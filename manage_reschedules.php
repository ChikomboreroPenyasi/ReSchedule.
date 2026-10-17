<?php
session_start();
require_once 'db.php';

// Ensure user is logged in as Lecturer or Administrator
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Lecturer' && $_SESSION['role'] !== 'Administrator')) {
    header("Location: login.php");
    exit();
}

$message = '';

// Handle Status Updates (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_note = trim((string) ($_POST['review_note'] ?? ''));

    if (isset($_POST['bulk_action']) && !empty($_POST['request_ids'])) {
        $request_ids = array_filter(array_map('intval', $_POST['request_ids']));
        $bulk_status = ($_POST['bulk_action'] === 'approve') ? 'Approved' : 'Rejected';

        if (!empty($request_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($request_ids), '?'));
                $stmt = $pdo->prepare("UPDATE reschedule_requests SET status = ?, review_note = COALESCE(NULLIF(?, ''), review_note), reviewed_at = NOW() WHERE id IN ($placeholders)");
                $params = array_merge([$bulk_status, $review_note], $request_ids);
                $stmt->execute($params);
                $message = count($request_ids) . ' request' . (count($request_ids) === 1 ? '' : 's') . ' marked as ' . $bulk_status . '.';
            } catch (PDOException $e) {
                $message = "Error updating requests: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action'], $_POST['request_id'])) {
        $request_id = intval($_POST['request_id']);
        $new_status = ($_POST['action'] === 'approve') ? 'Approved' : 'Rejected';

        try {
            $stmt = $pdo->prepare("UPDATE reschedule_requests SET status = :status, review_note = COALESCE(NULLIF(:review_note, ''), review_note), reviewed_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':status' => $new_status,
                ':review_note' => $review_note,
                ':id' => $request_id
            ]);
            $message = "Request #$request_id has been marked as $new_status.";
        } catch (PDOException $e) {
            $message = "Error updating request: " . $e->getMessage();
        }
    }
}

// Fetch Reschedule Requests with Student and Schedule Details
try {
    $query = "
        SELECT 
            r.id AS request_id,
            r.reason,
            r.preferred_time,
            r.status,
            r.created_at,
            r.review_note,
            r.reviewed_at,
            u.fullname AS student_name,
            u.student_number,
            s.course_code,
            s.course_name,
            s.day_of_week,
            s.start_time,
            s.end_time,
            rm.room_name
        FROM reschedule_requests r
        JOIN users u ON r.student_id = u.id
        JOIN schedules s ON r.schedule_id = s.id
        LEFT JOIN rooms rm ON s.room_id = rm.id
        ORDER BY r.created_at DESC
    ";
    $stmt = $pdo->query($query);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching requests: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReSchedule. - Manage Requests</title>
    <link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="dashboard-body">

<nav class="navbar">
    <div class="logo">ReSchedule<span>.</span></div>
    <div class="user-profile">
        <a href="dashboard.php" class="card-link">&larr; Dashboard</a>
        <a href="logout.php" class="btn-logout">Log Out</a>
    </div>
</nav>

<main class="dashboard-container">
    <header class="welcome-banner">
        <div>
            <span class="eyebrow">Academic review</span>
            <h1>Reschedule requests</h1>
            <p>Approve or reject timetable change requests submitted by students.</p>
        </div>
    </header>

    <?php if (!empty($message)): ?>
        <div class="alert-box success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="dash-card request-management-card">
        <div class="card-heading-row">
            <h3>Pending review</h3>
            <span class="section-hint"><?php echo count($requests); ?> submitted</span>
        </div>

        <form method="POST" id="bulkRequestForm" class="bulk-request-toolbar">
            <div class="bulk-toolbar">
                <label class="bulk-select-all"><input type="checkbox" id="selectAllRequests"> Select all</label>
                <input type="text" name="review_note" class="bulk-note-input" placeholder="Optional review note for selected requests" maxlength="255">
                <div class="bulk-actions">
                    <button type="button" class="btn-primary" data-bulk-action="approve">Approve selected</button>
                    <button type="button" class="btn-cancel" data-bulk-action="reject">Reject selected</button>
                </div>
            </div>
        </form>

        <div class="timetable-filters" style="margin-top: 0; margin-bottom: 1rem;">
            <input type="search" id="requestSearch" placeholder="Search student, course, or reason">
            <select id="requestStatusFilter">
                <option value="">All statuses</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>
            <button type="button" id="exportRequestsCsv" class="filter-export">Export CSV</button>
        </div>

        <div class="request-table-wrap">
            <table class="schedule-table" id="requestsTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllTableRows" aria-label="Select all requests"></th>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Current Slot</th>
                        <th>Reason</th>
                        <th>Preferred Time</th>
                        <th>Status</th>
                        <th>Review Note</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; color: var(--muted);">No reschedule requests submitted yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr class="request-row" data-status="<?php echo htmlspecialchars($req['status']); ?>" data-search="<?php echo htmlspecialchars(strtolower($req['student_name'] . ' ' . $req['course_code'] . ' ' . $req['course_name'] . ' ' . $req['reason'] . ' ' . ($req['preferred_time'] ?? ''))); ?>">
                                <td><input type="checkbox" class="request-checkbox" value="<?php echo $req['request_id']; ?>" aria-label="Select request #<?php echo $req['request_id']; ?>"></td>
                                <td>#<?php echo $req['request_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($req['student_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($req['student_number'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($req['course_code']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($req['course_name']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($req['day_of_week']); ?><br>
                                    <small><?php echo htmlspecialchars($req['start_time'] . ' - ' . $req['end_time']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($req['reason']); ?></td>
                                <td><?php echo htmlspecialchars($req['preferred_time'] ?? 'Flexible'); ?></td>
                                <td>
                                    <?php $statusClass = 'badge-pending'; if ($req['status'] === 'Approved') $statusClass = 'badge-approved'; if ($req['status'] === 'Rejected') $statusClass = 'badge-rejected'; ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($req['review_note'])): ?>
                                        <span class="request-note"><?php echo htmlspecialchars($req['review_note']); ?></span>
                                    <?php else: ?>
                                        <span class="request-note empty">No review note</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req['status'] === 'Pending'): ?>
                                        <div class="request-action-group">
                                            <button type="button" class="btn-primary request-action-btn" data-request-id="<?php echo $req['request_id']; ?>" data-action="approve">Approve</button>
                                            <button type="button" class="btn-cancel request-action-btn" data-request-id="<?php echo $req['request_id']; ?>" data-action="reject">Reject</button>
                                            <button type="button" class="request-detail-btn" data-request-detail='<?php echo htmlspecialchars(json_encode([
                                                'id' => $req['request_id'],
                                                'student' => $req['student_name'],
                                                'studentNumber' => $req['student_number'] ?? 'N/A',
                                                'course' => $req['course_code'] . ' - ' . $req['course_name'],
                                                'slot' => $req['day_of_week'] . ' · ' . $req['start_time'] . ' - ' . $req['end_time'],
                                                'reason' => $req['reason'],
                                                'preferredTime' => $req['preferred_time'] ?: 'Flexible',
                                                'status' => $req['status'],
                                                'note' => $req['review_note'] ?: 'No review note',
                                                'createdAt' => $req['created_at']
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>'>Details</button>
                                        </div>
                                    <?php else: ?>
                                        <div class="request-action-group">
                                            <em>Processed</em>
                                            <button type="button" class="request-detail-btn" data-request-detail='<?php echo htmlspecialchars(json_encode([
                                                'id' => $req['request_id'],
                                                'student' => $req['student_name'],
                                                'studentNumber' => $req['student_number'] ?? 'N/A',
                                                'course' => $req['course_code'] . ' - ' . $req['course_name'],
                                                'slot' => $req['day_of_week'] . ' · ' . $req['start_time'] . ' - ' . $req['end_time'],
                                                'reason' => $req['reason'],
                                                'preferredTime' => $req['preferred_time'] ?: 'Flexible',
                                                'status' => $req['status'],
                                                'note' => $req['review_note'] ?: 'No review note',
                                                'createdAt' => $req['created_at']
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>'>Details</button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="request-modal" id="requestDetailModal" hidden>
    <div class="request-modal-backdrop" data-close-request-modal></div>
    <section class="request-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="requestModalTitle">
        <div class="card-heading-row">
            <div>
                <span class="eyebrow">Request detail</span>
                <h2 id="requestModalTitle">Request #<span id="detailRequestId"></span></h2>
            </div>
            <button type="button" class="request-modal-close" data-close-request-modal aria-label="Close request details">&times;</button>
        </div>
        <div class="request-detail-grid">
            <div><span>Student</span><strong id="detailStudent"></strong><small id="detailStudentNumber"></small></div>
            <div><span>Course</span><strong id="detailCourse"></strong></div>
            <div><span>Current slot</span><strong id="detailSlot"></strong></div>
            <div><span>Preferred time</span><strong id="detailPreferredTime"></strong></div>
            <div><span>Submitted</span><strong id="detailCreatedAt"></strong></div>
            <div><span>Status</span><strong id="detailStatus"></strong></div>
        </div>
        <div class="request-detail-copy">
            <span>Student reason</span>
            <p id="detailReason"></p>
        </div>
        <div class="request-detail-copy">
            <span>Review note</span>
            <p id="detailNote"></p>
        </div>
    </section>
</div>

<script>
    const requestSearch = document.getElementById('requestSearch');
    const requestStatusFilter = document.getElementById('requestStatusFilter');
    const requestRows = Array.from(document.querySelectorAll('.request-row'));
    const selectAllRequests = document.getElementById('selectAllRequests');
    const selectAllTableRows = document.getElementById('selectAllTableRows');
    const requestCheckboxes = () => Array.from(document.querySelectorAll('.request-checkbox'));

    function syncSelectionState() {
        const boxes = requestCheckboxes();
        const checkedBoxes = boxes.filter(box => box.checked);
        const isAllSelected = boxes.length > 0 && checkedBoxes.length === boxes.length;

        if (selectAllRequests) selectAllRequests.checked = isAllSelected;
        if (selectAllTableRows) selectAllTableRows.checked = isAllSelected;
    }

    function collectSelectedRequestIds() {
        return requestCheckboxes().filter(box => box.checked).map(box => box.value);
    }

    function submitRequestAction(action, requestId) {
        const form = document.getElementById('bulkRequestForm');
        form.querySelectorAll('input[name="request_id"], input[name="action"], input[name="request_ids[]"], input[name="bulk_action"]').forEach(el => el.remove());

        const requestIdInput = document.createElement('input');
        requestIdInput.type = 'hidden';
        requestIdInput.name = 'request_id';
        requestIdInput.value = requestId;

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;

        form.appendChild(requestIdInput);
        form.appendChild(actionInput);
        form.submit();
    }

    function submitBulkAction(action) {
        const selectedIds = collectSelectedRequestIds();
        const form = document.getElementById('bulkRequestForm');
        form.querySelectorAll('input[name="request_id"], input[name="action"], input[name="request_ids[]"], input[name="bulk_action"]').forEach(el => el.remove());

        if (!selectedIds.length) {
            alert('Please select at least one request before applying a bulk action.');
            return;
        }

        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'request_ids[]';
            input.value = id;
            form.appendChild(input);
        });

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'bulk_action';
        actionInput.value = action;
        form.appendChild(actionInput);
        form.submit();
    }

    selectAllRequests?.addEventListener('change', () => {
        requestCheckboxes().forEach(box => box.checked = selectAllRequests.checked);
        syncSelectionState();
    });

    selectAllTableRows?.addEventListener('change', () => {
        requestCheckboxes().forEach(box => box.checked = selectAllTableRows.checked);
        syncSelectionState();
    });

    requestCheckboxes().forEach(box => {
        box.addEventListener('change', syncSelectionState);
    });

    document.querySelectorAll('[data-bulk-action]').forEach(button => {
        button.addEventListener('click', () => submitBulkAction(button.dataset.bulkAction));
    });

    document.querySelectorAll('.request-action-btn').forEach(button => {
        button.addEventListener('click', () => submitRequestAction(button.dataset.action, button.dataset.requestId));
    });

    const requestDetailModal = document.getElementById('requestDetailModal');
    const detailFields = {
        id: document.getElementById('detailRequestId'),
        student: document.getElementById('detailStudent'),
        studentNumber: document.getElementById('detailStudentNumber'),
        course: document.getElementById('detailCourse'),
        slot: document.getElementById('detailSlot'),
        preferredTime: document.getElementById('detailPreferredTime'),
        createdAt: document.getElementById('detailCreatedAt'),
        status: document.getElementById('detailStatus'),
        reason: document.getElementById('detailReason'),
        note: document.getElementById('detailNote')
    };

    function closeRequestModal() {
        if (!requestDetailModal) return;
        requestDetailModal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('[data-request-detail]').forEach(button => {
        button.addEventListener('click', () => {
            const detail = JSON.parse(button.dataset.requestDetail);
            Object.keys(detailFields).forEach(key => {
                if (detailFields[key]) detailFields[key].textContent = detail[key] || '';
            });
            requestDetailModal.hidden = false;
            document.body.classList.add('modal-open');
            document.querySelector('.request-modal-close')?.focus();
        });
    });

    document.querySelectorAll('[data-close-request-modal]').forEach(element => {
        element.addEventListener('click', closeRequestModal);
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeRequestModal();
    });

    function filterRequestRows() {
        const q = (requestSearch?.value || '').toLowerCase();
        const status = requestStatusFilter?.value || '';

        requestRows.forEach(row => {
            const matchesText = !q || row.dataset.search.includes(q);
            const matchesStatus = !status || row.dataset.status === status;
            row.hidden = !(matchesText && matchesStatus);
        });
    }

    requestSearch?.addEventListener('input', filterRequestRows);
    requestStatusFilter?.addEventListener('change', filterRequestRows);
    syncSelectionState();

    document.getElementById('exportRequestsCsv')?.addEventListener('click', () => {
        const visibleRows = requestRows.filter(row => !row.hidden);
        const csv = [['ID','Student','Course','Current Slot','Reason','Preferred Time','Status'], ...visibleRows.map(row => Array.from(row.cells).slice(1, 8).map(cell => '"' + cell.textContent.trim().replace(/"/g, '""') + '"'))]
            .map(row => row.join(','))
            .join('\n');

        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        link.download = 'reschedule_requests.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
    });
</script>

</body>
</html>