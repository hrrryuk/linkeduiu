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

 // Job Card Toggle Logic
document.querySelectorAll('.job-card').forEach(card => {
    const view_btn = card.querySelector('button[name="job_card_action_requirements_btn"]');
    const edit_btn = card.querySelector('button[name="job_card_action_attachments_btn"]');
    const view_action = card.querySelector('.job-card-action-requirements');
    const edit_action = card.querySelector('.job-card-action-attachments');

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

// Endorsement Selection Logic
document.addEventListener('DOMContentLoaded', () => {
  const endorsementContainers = document.querySelectorAll('.select-endorsements');

  endorsementContainers.forEach(container => {
    const options = container.querySelectorAll('.endorsement-options');
    const hiddenInput = container.closest('form').querySelector('input[name="selected_endorsements"]');

    options.forEach(option => {
      option.addEventListener('click', () => {
        option.classList.toggle('selected');

        // Collect selected faculty IDs into an array
        const selected = Array.from(container.querySelectorAll('.endorsement-options.selected'))
          .map(el => el.dataset.facultyId);

        if (selected.length > 5) {
          option.classList.remove('selected');
          alert('You can select up to 5 endorsements only.');
          return;
        }

        // Update hidden input with JSON string of selected endorsements
        hiddenInput.value = JSON.stringify(selected);
      });
    });
  });
});


// Expand reuqirements when there is a error message
document.addEventListener('DOMContentLoaded', () => {
  // Find all job cards
  const jobCards = document.querySelectorAll('.job-card');

  jobCards.forEach(card => {
    const messageLabel = card.querySelector('.job-card-action-requirements p label');
    const messageSpan = card.querySelector('.job-card-action-requirements p span');

    // If message span exists (means error message is shown)
    if (messageSpan && messageSpan.textContent.trim() !== '') {
      // Unhide the requirements section
      const reqSection = card.querySelector('.job-card-action-requirements');
      if (reqSection && reqSection.classList.contains('hidden')) {
        reqSection.classList.remove('hidden');
      }
    }
  });
});