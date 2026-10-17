<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'], true)) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_event') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $eventType = trim($_POST['event_type'] ?? 'Other');
            $startDate = trim($_POST['start_date'] ?? '');
            $startTime = trim($_POST['start_time'] ?? '00:00');
            $endDate = trim($_POST['end_date'] ?? $startDate);
            $endTime = trim($_POST['end_time'] ?? '23:59');
            $venue = trim($_POST['venue'] ?? '');
            $visibility = trim($_POST['visibility'] ?? 'Everyone');
            $isAllDay = isset($_POST['is_all_day']) ? 1 : 0;

            if ($title === '' || $startDate === '') {
                throw new RuntimeException('Event title and start date are required.');
            }

            $startDatetime = $startDate . ' ' . ($isAllDay ? '00:00' : $startTime) . ':00';
            $endDatetime = $endDate . ' ' . ($isAllDay ? '23:59' : $endTime) . ':00';

            $start = new DateTimeImmutable($startDatetime);
            $end = new DateTimeImmutable($endDatetime);
            if ($end < $start) {
                throw new RuntimeException('The event end must be after its start.');
            }

            $stmt = $pdo->prepare('INSERT INTO events (title, description, event_type, start_datetime, end_datetime, is_all_day, venue, visibility, created_by) VALUES (:title, :description, :event_type, :start_datetime, :end_datetime, :is_all_day, :venue, :visibility, :created_by)');
            $stmt->execute([
                ':title' => $title,
                ':description' => $description ?: null,
                ':event_type' => $eventType,
                ':start_datetime' => $start->format('Y-m-d H:i:s'),
                ':end_datetime' => $end->format('Y-m-d H:i:s'),
                ':is_all_day' => $isAllDay,
                ':venue' => $venue ?: null,
                ':visibility' => $visibility,
                ':created_by' => (int) $_SESSION['user_id']
            ]);
            $message = 'Event created successfully.';
        } elseif ($action === 'delete_event') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            if ($eventId < 1) {
                throw new RuntimeException('Invalid event selected.');
            }
            $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
            $stmt->execute([':id' => $eventId]);
            $message = 'Event deleted successfully.';
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$events = $pdo->query('SELECT e.*, u.fullname AS creator_name FROM events e LEFT JOIN users u ON e.created_by = u.id ORDER BY e.start_datetime ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReSchedule. - Events</title>
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
            <span class="eyebrow">Shared calendar</span>
            <h1>Events scheduler</h1>
            <p>Create meetings, holidays, deadlines, seminars, and other shared academic events.</p>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert-box <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="event-management-grid">
        <article class="dash-card">
            <div class="card-heading-row">
                <h3>Create event</h3>
                <span class="section-hint">Visible to <?php echo htmlspecialchars($_SESSION['role']); ?> users</span>
            </div>
            <form method="POST" class="event-form">
                <input type="hidden" name="action" value="create_event">
                <div class="form-group">
                    <label for="title">Event title</label>
                    <input id="title" name="title" type="text" maxlength="180" placeholder="e.g. Faculty meeting" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="event_type">Event type</label>
                        <select id="event_type" name="event_type">
                            <option>Meeting</option>
                            <option>Holiday</option>
                            <option>Deadline</option>
                            <option>Seminar</option>
                            <option>Workshop</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="visibility">Visibility</label>
                        <select id="visibility" name="visibility">
                            <option>Everyone</option>
                            <option>Students</option>
                            <option>Staff</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000" placeholder="Add useful context for attendees"></textarea>
                </div>
                <div class="event-date-grid">
                    <div class="form-group">
                        <label for="start_date">Start date</label>
                        <input id="start_date" name="start_date" type="date" required>
                    </div>
                    <div class="form-group">
                        <label for="start_time">Start time</label>
                        <input id="start_time" name="start_time" type="time" value="08:00">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End date</label>
                        <input id="end_date" name="end_date" type="date">
                    </div>
                    <div class="form-group">
                        <label for="end_time">End time</label>
                        <input id="end_time" name="end_time" type="time" value="09:00">
                    </div>
                </div>
                <label class="event-all-day"><input type="checkbox" name="is_all_day" id="is_all_day"> All-day event</label>
                <div class="form-group">
                    <label for="venue">Venue</label>
                    <input id="venue" name="venue" type="text" maxlength="180" placeholder="e.g. Senate room or Main campus">
                </div>
                <button type="submit" class="btn-submit">Create event</button>
            </form>
        </article>

        <article class="dash-card">
            <div class="card-heading-row">
                <h3>Scheduled events</h3>
                <span class="section-hint"><?php echo count($events); ?> event<?php echo count($events) === 1 ? '' : 's'; ?></span>
            </div>
            <?php if (empty($events)): ?>
                <p class="empty-state">No events have been created yet.</p>
            <?php else: ?>
                <div class="event-list">
                    <?php foreach ($events as $event): ?>
                        <article class="event-list-item">
                            <div class="event-list-date">
                                <strong><?php echo htmlspecialchars(date('d', strtotime($event['start_datetime']))); ?></strong>
                                <span><?php echo htmlspecialchars(date('M', strtotime($event['start_datetime']))); ?></span>
                            </div>
                            <div class="event-list-content">
                                <div class="event-list-heading">
                                    <div>
                                        <span class="event-type-badge event-type-<?php echo htmlspecialchars(strtolower($event['event_type'])); ?>"><?php echo htmlspecialchars($event['event_type']); ?></span>
                                        <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Delete this event?');">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>">
                                        <button type="submit" class="event-delete-btn" aria-label="Delete event">&times;</button>
                                    </form>
                                </div>
                                <p><?php echo htmlspecialchars($event['description'] ?: 'No description added.'); ?></p>
                                <small><?php echo htmlspecialchars(date('D, j M Y H:i', strtotime($event['start_datetime']))); ?> - <?php echo htmlspecialchars(date('H:i', strtotime($event['end_datetime']))); ?><?php echo $event['venue'] ? ' · ' . htmlspecialchars($event['venue']) : ''; ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>
<script>
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const allDay = document.getElementById('is_all_day');
    startDate?.addEventListener('change', () => {
        if (endDate && !endDate.value) endDate.value = startDate.value;
    });
    allDay?.addEventListener('change', () => {
        document.getElementById('start_time').disabled = allDay.checked;
        document.getElementById('end_time').disabled = allDay.checked;
    });
</script>
</body>
</html>
