<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReSchedule. | University Timetabling Portal</title>
    <link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg">
    <meta name="description" content="ReSchedule. helps students, lecturers, and administrators manage timetables, room bookings, and reschedule requests with clarity and speed.">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body>
    <header class="navbar">
        <div class="logo">ReSchedule<span>.</span></div>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="#features">Features</a>
            <a href="#platforms">Platforms</a>
            <a href="#workspaces">Workspaces</a>
            <a href="login.php" class="btn-secondary">Log In</a>
            <a href="signup.php" class="btn-primary">Get Started</a>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-container">
            <div class="hero-grid">
                <div>
                    <span class="badge">University Scheduling Platform</span>
                    <h1>Smarter class, exam, and room planning for campus life.</h1>
                    <p>ReSchedule. gives universities a clearer way to manage timetables, room availability, reschedule requests, and student communication in one dependable system.</p>
                    <div class="hero-actions">
                        <a href="signup.php" class="btn-primary-lg">Create Account</a>
                        <a href="login.php" class="btn-secondary-lg">Member Login</a>
                    </div>
                </div>

                <div class="hero-panel" aria-label="Platform performance summary">
                    <div class="mini-card">
                        <h4>Active campus schedules</h4>
                        <strong>1,200+</strong>
                        <span>Booked entries managed this term</span>
                    </div>
                    <div class="mini-card">
                        <h4>Conflict checks</h4>
                        <strong>98.7%</strong>
                        <span>Auto-validation on schedule updates</span>
                    </div>
                    <div class="mini-card">
                        <h4>Student turnaround</h4>
                        <strong>2x faster</strong>
                        <span>Fewer scheduling delays and requests</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="features-section">
        <div class="section-header">
            <h2>Built for modern university operations</h2>
            <p>Designed to make timetable management simple, transparent, and easier for everyone on campus.</p>
        </div>

        <div class="features-grid">
            <article class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Fast conflict detection</h3>
                <p>Reduce clashes between rooms, lecturers, and student cohorts before they reach the timetable.</p>
            </article>

            <article class="feature-card">
                <div class="feature-icon">🧭</div>
                <h3>Clear daily visibility</h3>
                <p>Students and staff can quickly access class times, exam slots, and venue information with less confusion.</p>
            </article>

            <article class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Operational reporting</h3>
                <p>Administrators see the status of schedules, requests, and room usage at a glance without extra manual work.</p>
            </article>
        </div>
    </section>

    <section id="platforms" class="platforms-section">
        <div class="section-header">
            <h2>One system across campus roles</h2>
            <p>Support the full academic workflow across students, lecturers, and administrators.</p>
        </div>

        <div class="platforms-grid">
            <article class="platform-card">
                <span class="platform-tag">Student portal</span>
                <h3>See your timetable clearly</h3>
                <ul>
                    <li>Weekly class view by day and time</li>
                    <li>Assessment and exam visibility</li>
                    <li>Easy reschedule request submission</li>
                    <li>Program-specific schedule access</li>
                </ul>
            </article>

            <article class="platform-card accent">
                <span class="platform-tag">Lecturer tools</span>
                <h3>Stay organized and responsive</h3>
                <ul>
                    <li>Review teaching blocks quickly</li>
                    <li>Manage room or time changes</li>
                    <li>Monitor reschedule requests</li>
                    <li>Keep departments aligned</li>
                </ul>
            </article>

            <article class="platform-card">
                <span class="platform-tag">Admin center</span>
                <h3>Control the academic calendar</h3>
                <ul>
                    <li>Manage programs and resources</li>
                    <li>Update timetable entries easily</li>
                    <li>Track pending requests</li>
                    <li>Publish schedules with confidence</li>
                </ul>
            </article>
        </div>
    </section>

    <section id="workspaces" class="workspace-templates">
        <div class="section-header">
            <h2>Choose your campus workflow</h2>
            <p>Every role gets a launch point designed for faster action and clearer decision-making.</p>
        </div>

        <div class="workspace-tabs" role="tablist" aria-label="Campus role tabs">
            <button class="workspace-tab is-active" id="student-tab" role="tab" aria-selected="true" aria-controls="student-workspace" data-workspace="student">Student</button>
            <button class="workspace-tab" id="lecturer-tab" role="tab" aria-selected="false" aria-controls="lecturer-workspace" data-workspace="lecturer">Lecturer</button>
            <button class="workspace-tab" id="admin-tab" role="tab" aria-selected="false" aria-controls="admin-workspace" data-workspace="admin">Administrator</button>
        </div>

        <div class="workspace-template-grid">
            <article class="workspace-template" id="student-workspace" role="tabpanel" aria-labelledby="student-tab" data-workspace-panel="student">
                <span class="workspace-kicker">For students</span>
                <h3>Know your academic week.</h3>
                <p>View classes, review exam dates, and send a request when your schedule needs attention.</p>
                <a href="signup.php" class="template-link">Create student account <span>→</span></a>
            </article>

            <article class="workspace-template" id="lecturer-workspace" role="tabpanel" aria-labelledby="lecturer-tab" data-workspace-panel="lecturer" hidden>
                <span class="workspace-kicker">For lecturers</span>
                <h3>Keep teaching on track.</h3>
                <p>Monitor room use, review requests, and manage teaching time with more confidence.</p>
                <a href="login.php" class="template-link">Open lecturer portal <span>→</span></a>
            </article>

            <article class="workspace-template" id="admin-workspace" role="tabpanel" aria-labelledby="admin-tab" data-workspace-panel="admin" hidden>
                <span class="workspace-kicker">For administrators</span>
                <h3>Run the timetable with clarity.</h3>
                <p>Import course data, manage rooms, and publish schedules that are easier for everyone to use.</p>
                <a href="login.php" class="template-link">Manage timetable <span>→</span></a>
            </article>
        </div>
    </section>

    <section class="cta-banner">
        <h2>Ready to simplify your campus timetable?</h2>
        <p>Give staff and students a more reliable system they will actually enjoy using.</p>
        <a href="signup.php" class="btn-primary-lg">Get Started</a>
    </section>

    <footer>
        <p>© <?php echo date('Y'); ?> ReSchedule. Timetabling System. Built to support student and staff scheduling.</p>
    </footer>

    <script src="js/main.js"></script>
    <script>
        document.querySelectorAll('.workspace-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const workspace = tab.dataset.workspace;

                document.querySelectorAll('.workspace-tab').forEach(function (item) {
                    const active = item === tab;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                document.querySelectorAll('[data-workspace-panel]').forEach(function (panel) {
                    panel.hidden = panel.dataset.workspacePanel !== workspace;
                });
            });
        });
    </script>
</body>
</html>
