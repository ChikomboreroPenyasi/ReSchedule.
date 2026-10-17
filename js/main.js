document.addEventListener('DOMContentLoaded', function () {
    const signupForm = document.getElementById('signupForm');

    if (signupForm) {
        signupForm.addEventListener('submit', function (e) {
            const fullname = document.getElementById('fullname')?.value.trim();
            const studentNumber = document.getElementById('student_number')?.value.trim();
            const role = document.getElementById('role')?.value;
            const password = document.getElementById('password')?.value || '';
            const confirmPassword = document.getElementById('confirm_password')?.value || '';

            if (!fullname || !studentNumber || !role || !password || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all required fields before submitting.');
                return;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
    }
});