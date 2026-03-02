<?php
require 'helpers.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>
        <div class="container header">
            <img src="../uploads/img/uiu_banner.png">
            <h1></h1>
            <form action="/logout.php" method="post" onsubmit="return confirm('Are you sure you want to logout?')">
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </header>
    <main>
        <div class="container main">
            <!-- Profile and Endorsements -->
            <div class="profile-endorsements-wrapper">
                <!-- Profile -->
                <section class="profile">
                    <img src="../uploads/img/<?= htmlspecialchars($data['student_photo'] ?? 'user.png') ?>"
                        onerror="this.onerror=null;this.src='../uploads/img/user.png';">
                    <div class="profile-info">
                        <div class="profile-attributes">
                            <p>Name</p>
                            <p>Email</p>
                        </div>
                        <div class="profile-attributes">
                            <p>&nbsp;:&nbsp;&nbsp;</p>
                            <p>&nbsp;:&nbsp;&nbsp;</p>
                        </div>
                        <div>
                            <p><?= e($data['student_name']) ?></p>
                            <p><?= e($data['student_email']) ?></p>
                        </div>
                    </div>
                    <button type="button" id="profile-edit-btn">Edit Profile</button>
                    <form method="post" enctype="multipart/form-data" id="profile-update-form">
                        <label>Upload Profile Picture</label>
                        <input type="file" name="student_photo" accept="image/*" />
                        <label>Upload Resume</label>
                        <input type="file" name="student_resume" accept="application/pdf">
                        <button type="submit" name="update_profile">Update Profile</button>
                    </form>
                </section>
                <!-- Endorsements -->
                <section class="endorsements">
                    <div>
                        <h2>ENDORSEMENTS</h2>
                        <p>[<?= count($data['endorsements']) ?>]</p>
                    </div>
                    <div>
                        <?php foreach ($data['endorsements'] as $endorser): ?>
                            <img src="../uploads/img/<?= e($endorser['faculty_photo'] ?? 'user.png') ?>">
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
            <!-- Applied Jobs -->
            <section class="applied-jobs">
                <h2>APPLIED JOBS</h2>
                <div class="applied-jobs-grid">
                    <?php foreach ($data['applied_jobs'] as $job): ?>
                        <article class="applied-job-card">
                            <div class="applied-job-card-info">
                                <div class="applied-job-card-info-rows">
                                    <div class="applied-job-card-attributes">
                                        <p>Faculty</p>
                                        <p>Contact</p>
                                        <p>Course</p>
                                    </div>
                                    <div class="applied-job-card-attributes">
                                        <p>&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;</p>
                                        <p>&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;</p>
                                        <p>&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;</p>
                                    </div>
                                    <div>
                                        <p><?= e($job['faculty_name']) ?> (<?= e($job['faculty_id']) ?>)</p>
                                        <p><?= e($job['faculty_email']) ?></p>
                                        <p>(<?= e($job['job_role']) ?>) <?= e($job['course_code']) ?> - <?= e($job['course_name']) ?> (<?= e($job['section']) ?>)</p>
                                    </div>
                                </div>
                                <div class="applied-job-card-info-rows">
                                    <div class="applied-job-card-attributes">
                                        <p>Schedule</p>
                                    </div>
                                    <div class="applied-job-card-attributes">
                                        <p>&nbsp;:&nbsp;&nbsp;</p>
                                    </div>
                                    <div>
                                        <p><?= e($job['schedule_text']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="applied-job-card-footer">
                                <img src="../uploads/img/<?= e($job['faculty_photo'] ?? 'user.png') ?>">
                                <div class="applied-job-card-actions">
                                    <label><?= ucfirst($job['status']) ?></label>
                                    <form method="post">
                                        <input type="hidden" name="faculty_id" value="<?= e($job['faculty_id']) ?>">
                                        <input type="hidden" name="course_code" value="<?= e($job['course_code']) ?>">
                                        <input type="hidden" name="section" value="<?= e($job['section']) ?>">

                                        <?php if ($job['status'] === 'pending' && $job['cancel_allowed']): ?>
                                            <button type="submit" name="job_action" value="cancel">Cancel</button>
                                        <?php endif; ?>
                                        <?php if ($job['status'] === 'accepted'): ?>
                                            <button type="submit" name="job_action" value="resign" <?= $job['resign_request'] ? 'disabled' : '' ?> onclick="return confirm('Are you sure you want to submit resign request?');">
                                                <?= $job['resign_request'] ? 'Requested' : 'Resign' ?>
                                            </button>
                                        <?php endif; ?>

                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <!-- Available Jobs -->
            <section class="available-jobs">
                <h2>AVAILABLE JOBS</h2>
                <!-- Job Filters -->
                <?php $dropdowns = get_job_filter_dropdowns($data['time_slots'] ?? []); ?>
                <form method="post" class="job-filters" enctype="multipart/form-data">
                    <?php foreach ($dropdowns as $name => $config): ?>
                        <label>
                            <?= e($config['label']) ?>: &nbsp;
                            <select name="<?= $name ?>">
                                <?php foreach ($config['options'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>"
                                        <?= selected($value, $filters[$name] ?? $config['default']) ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endforeach; ?>
                    <label class="checkbox-eligible">
                        <input type="checkbox" name="eligible_jobs" <?= checked('eligible_jobs', $filters ?? []) ?> />
                        <span></span>
                        &nbsp;&nbsp;Eligible Only
                    </label>
                    <button type="submit" name="filter_jobs">Apply Filters</button>
                    <button type="submit" name="sort_jobs">Recommended Jobs</button>
                </form>
                <!-- Job List -->
                <?php foreach ($data['available_jobs'] as $job): ?>
                    <article class="job-card">
                        <form method="post" enctype="multipart/form-data">
                            <!-- Job Informations -->
                            <div class="job-card-info">
                                <div class="job-card-info-text">
                                    <h3><?= e($job['course_code']) ?> - <?= e($job['course_name']) ?> (<?= e($job['section']) ?>)</h3>
                                    <p>Role : <?= e($job['job_role']) ?> &nbsp;|&nbsp; Time : <?= e($job['schedule_text']) ?></p>
                                </div>
                                <div class="job-card-info-actions">
                                    <button type="button" name="job_card_action_requirements_btn">Requirements</button>
                                    <button type="button" name="job_card_action_attachments_btn">Attachments</button>

                                    <input type="hidden" name="faculty_id" value="<?= e($job['faculty_id']) ?>">
                                    <input type="hidden" name="course_code" value="<?= e($job['course_code']) ?>">
                                    <input type="hidden" name="section" value="<?= e($job['section']) ?>">
                                    <button type="submit" name="job_action" value="apply">APPLY</button>
                                </div>
                            </div>
                            <!-- Requirements -->
                            <div class="job-card-action-requirements hidden">
                                <p><label>Required CGPA :</label> &nbsp; <?= e($job['min_cgpa']) ?></p>
                                <p><label>Completed Credit :</label> &nbsp; <?= e($job['min_credit']) ?></p>
                                <p><label>Obtained Grade :</label> &nbsp; <?= e($job['min_grade']) ?></p>
                                <p><label>Deadline :</label> &nbsp; <?= e($job['deadline']) ?></p>
                                <p><label>Message :</label> &nbsp;
                                    <!-- Flash messages -->
                                    <?php if (
                                        isset($data['apply_error_job']) &&
                                        $data['apply_error_job']['faculty_id'] === $job['faculty_id'] &&
                                        $data['apply_error_job']['course_code'] === $job['course_code'] &&
                                        $data['apply_error_job']['section'] === $job['section']
                                    ): ?>
                                        <span style="color:red;"><?= e($data['apply_error']) ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <!-- Attachments -->
                            <div class="job-card-action-attachments hidden">
                                <div>
                                    <label>Resume : &nbsp;</label>
                                    <input type="file" name="selected_resume" accept="application/pdf">
                                </div>
                                <div>
                                    <label>Choose Endorsements : &nbsp;</label>
                                    <div class="select-endorsements">
                                        <?php foreach ($data['endorsements'] as $endorser): ?>
                                            <div class="endorsement-options" data-faculty-id="<?= e($endorser['faculty_id']) ?>">
                                                <img src="../uploads/img/<?= e($endorser['faculty_photo']) ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="selected_endorsements">
                                </div>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    </main>
    <script src="script.js"></script>
</body>

</html>