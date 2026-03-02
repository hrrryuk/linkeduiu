document.querySelectorAll('.job-card').forEach(card => {
    const edit_btn = card.querySelector('button[name="job_action_edit_btn"]');
    const view_btn = card.querySelector('button[name="job_action_view_btn"]');
    const edit_action = card.querySelector('.job-action-edit');
    const view_action = card.querySelector('.job-action-view');

    edit_btn.addEventListener('click', () => {
        // Close candidates if open
        if (!view_action.classList.contains('hidden')) {
            view_action.classList.add('hidden');
        }
        // Toggle edit
        edit_action.classList.toggle('hidden');
    });

    view_btn.addEventListener('click', () => {
        // Close edit if open
        if (!edit_action.classList.contains('hidden')) {
            edit_action.classList.add('hidden');
        }
        // Toggle candidates
        view_action.classList.toggle('hidden');
    });
});

// Run after the DOM has loaded
document.addEventListener("DOMContentLoaded", function() {
    const edit_btn = document.getElementById("profile-edit-btn");
    const form = document.getElementById("profile-update-form");

    // Initially hide the form
    form.style.display = "none";

    // When edit button is clicked
    edit_btn.addEventListener("click", function() {
        form.style.display = "flex"; // Show the form
        edit_btn.style.display = "none"; // Hide the edit button
    });
});