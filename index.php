<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule - Automated Timetabling System</title>
    <link rel="stylesheet" href="css/style.css">
    
</head>
<body>

    <header class="navbar">
        <div class="logo">Reschedule<span>.</span></div>
        <nav class="nav-links">
            <a href="#features">Features</a>
            <a href="#platforms">Platforms</a>
            <a href="#workspaces">Workspaces</a>
            <a href="login.php" class="btn-secondary">Log In</a>
            <a href="signup.php" class="btn-primary">Get Started</a>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-container">
            <span class="badge">Next-Gen Timetabling Platform</span>
            <h1>Smarter Class & Exam Scheduling for Higher Ed</h1>
            <p>Reschedule replaces manual timetable assembly with an automated engine that resolves room conflicts, lecturer double-booking, and course overlaps across web and mobile.</p>
            <div class="hero-actions">
                <a href="signup.php" class="btn-primary-lg">Create Account</a>
                <a href="splash.html" class="btn-secondary-lg">Preview Mobile App</a>
            </div>
        </div>
    </section>

    <section id="features" class="features-section">
        <div class="section-header">
            <h2>Built to Eliminate Scheduling Friction</h2>
            <p>Engineered for administrative staff, lecturers, and students.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Conflict-Free Engine</h3>
                <p>Automated validation prevents room double-booking, venue capacity overflows, and lecturer time collisions instantly.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔄</div>
                <h3>Real-Time Sync</h3>
                <p>Schedule updates, venue shifts, and time modifications push dynamically to both web accounts and mobile devices.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Android Companion</h3>
                <p>Students and faculty access their personalized daily timetable, receive shift notifications, and view offline schedules.</p>
            </div>
        </div>
    </section>

    <section id="platforms" class="platforms-section">
        <div class="section-header">
            <h2>Unified Across Platforms</h2>
            <p>Access your timetable anywhere, whether on a desktop browser or a mobile device.</p>
        </div>

        <div class="platforms-grid">
            <div class="platform-card">
                <span class="platform-tag">Web Portal</span>
                <h3>Administrative Control Center</h3>
                <ul>
                    <li>Full master schedule generation & room allocation</li>
                    <li>Lecturer availability management</li>
                    <li>PDF and CSV timetable exports</li>
                    <li>User role management (Admin, Staff, Student)</li>
                </ul>
            </div>

            <div class="platform-card accent">
                <span class="platform-tag">Android App</span>
                <h3>On-the-Go Schedule Access</h3>
                <ul>
                    <li>Instant daily and weekly class views</li>
                    <li>Push notifications for emergency venue changes</li>
                    <li>Offline timetable caching for fast viewing</li>
                    <li>Quick account sign-up and authentication</li>
                </ul>
            </div>
        </div>
    </section>

    <section id="workspaces" class="workspace-templates">
        <div class="section-header">
            <h2>Choose your way into the timetable</h2>
            <p>Every role gets a focused starting point, with the right tools ready when you sign in.</p>
        </div>
        <div class="workspace-tabs" role="tablist" aria-label="Workspace roles">
            <button class="workspace-tab is-active" id="student-tab" role="tab" aria-selected="true" aria-controls="student-workspace" data-workspace="student">Student</button>
            <button class="workspace-tab" id="lecturer-tab" role="tab" aria-selected="false" aria-controls="lecturer-workspace" data-workspace="lecturer">Lecturer</button>
            <button class="workspace-tab" id="admin-tab" role="tab" aria-selected="false" aria-controls="admin-workspace" data-workspace="admin">Administrator</button>
        </div>
        <div class="workspace-template-grid">
            <article class="workspace-template workspace-template-student" id="student-workspace" role="tabpanel" aria-labelledby="student-tab" data-workspace-panel="student">
                <span class="workspace-kicker">For students</span>
                <h3>Know what is next.</h3>
                <p>See your weekly classes, upcoming assessments, and request a change when life gets in the way.</p>
                <a href="signup.php" class="template-link">Create student account <span>&rarr;</span></a>
            </article>
            <article class="workspace-template workspace-template-staff" id="lecturer-workspace" role="tabpanel" aria-labelledby="lecturer-tab" data-workspace-panel="lecturer" hidden>
                <span class="workspace-kicker">For lecturers</span>
                <h3>Keep teaching moving.</h3>
                <p>Review teaching slots, manage room changes, and respond to student reschedule requests.</p>
                <a href="login.php" class="template-link">Open staff portal <span>&rarr;</span></a>
            </article>
            <article class="workspace-template workspace-template-admin" id="admin-workspace" role="tabpanel" aria-labelledby="admin-tab" data-workspace-panel="admin" hidden>
                <span class="workspace-kicker">For administrators</span>
                <h3>Launch the whole schedule.</h3>
                <p>Import a timetable, map programmes, resolve conflicts, and publish a dependable campus schedule.</p>
                <a href="login.php" class="template-link">Manage timetable <span>&rarr;</span></a>
            </article>
        </div>
    </section>

    <section class="cta-banner">
        <h2>Ready to simplify your institution's schedule?</h2>
        <p>Get started with Reschedule today across web and mobile.</p>
        <a href="signup.php" class="btn-primary-lg">Sign Up Now</a>
    </section>

    <footer>
        <div class="footer-container">
            <p>&copy; <?php echo date('Y'); ?> Reschedule Timetabling System. All rights reserved.</p>
        </div>
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