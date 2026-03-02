<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
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
            <section class="profile">
                <img src="../uploads/img/<?= htmlspecialchars($data['faculty_photo'] ?? 'user.png') ?>"
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
                        <p><?= htmlspecialchars($data['faculty_name']) ?></p>
                        <p><?= htmlspecialchars($data['faculty_email']) ?></p>
                    </div>
                </div>
                <button type="button" id="profile-edit-btn">Edit Profile</button>
                <form method="post" enctype="multipart/form-data" id="profile-update-form">
                    <label>Change Profile Picture</label>
                    <input type="file" name="faculty_photo" accept="image/*" required />
                    <button type="submit" name="update_profile">Update Profile</button>
                </form>
            </section>
            <section class="job-post">
                <h2>POST A NEW JOB</h2>
                <form method="post" class="job-post-form">
                    <div class="job-post-form-rows">
                        <div>
                            <label>Job Role</label>
                            &nbsp;
                            <select name="select_job_role">
                                <option value="UA">UA</option>
                                <option value="GRADER">GRADER</option>
                            </select>
                        </div>
                        <div>
                            <label>Select Course</label>
                            &nbsp;
                            <select name="select_course">
                                <?php foreach ($data['courses'] as $section): ?>
                                    <option value="<?= htmlspecialchars($section['course_code']) ?>|<?= htmlspecialchars($section['section']) ?>">
                                        <?= htmlspecialchars($section['course_code']) ?> - <?= htmlspecialchars($section['course_name']) ?> (<?= htmlspecialchars($section['section']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="job-post-form-rows">
                        <div>
                            <label>Required CGPA</label>
                            &nbsp;
                            <input type="text" name="required_cgpa" required>
                        </div>
                        <div>
                            <label>Completed Credit</label>
                            &nbsp;
                            <input type="text" name="completed_credit" required>
                        </div>
                        <div>
                            <label>Obtained Grade</label>
                            &nbsp;
                            <input type="text" name="obtained_grade" required>
                        </div>
                    </div>
                    <div class="job-post-form-rows">
                        <div>
                            <label>Deadline</label>
                            &nbsp;
                            <input type="date" name="deadline" required>
                        </div>
                        <button type="submit" name="publish">Publish</button>
                    </div>
                </form>
                <!-- errors -->
                <?php if (isset($_SESSION['alert']) && $_SESSION['alert']['where'] === 'job-post-form'): ?>
                    <div class="flash <?= htmlspecialchars($_SESSION['alert']['type']) ?>">
                        <?= $_SESSION['alert']['message'] ?>
                    </div>
                    <?php unset($_SESSION['alert']); ?>
                <?php endif; ?>
            </section>
            <section class="job-posted">
                <h2>PUBLISHED JOBS</h2>
                <?php foreach ($data['jobs'] as $job): ?>
                    <article class="job-card">
                        <div class="job-info">
                            <div class="job-info-text">
                                <h3><?= htmlspecialchars($job['course_code']) ?> - <?= htmlspecialchars($job['course_name']) ?> (<?= htmlspecialchars($job['section']) ?>)</h3>
                                <div>
                                    <p>Role:&nbsp;</p>
                                    <p><?= htmlspecialchars($job['job_role']) ?></p>
                                    <span>&nbsp;|&nbsp;</span>
                                    <p>Time:&nbsp;</p>
                                    <p><?= htmlspecialchars($job['schedule_text']) ?></p>
                                    <span>&nbsp;|&nbsp;</span>
                                    <p>Deadline:&nbsp;</p>
                                    <p><?= htmlspecialchars($job['deadline']) ?></p>
                                </div>
                            </div>
                            <div class="job-info-actions">
                                <button type="button" name="job_action_view_btn">Applicants</button>
                                <form method="post">
                                    <button type="submit" name="sort_applicants" value="<?= htmlspecialchars($job['course_code']) ?>|<?= htmlspecialchars($job['section']) ?>|<?= htmlspecialchars($job['job_role']) ?>">Smart Sort</button>
                                </form>
                                <button type="button" name="job_action_edit_btn">Edit</button>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this job and all its applicants?');">
                                    <input type="hidden" name="job_action_delete" value="<?= htmlspecialchars($job['course_code']) ?>|<?= htmlspecialchars($job['section']) ?>|<?= htmlspecialchars($job['job_role']) ?>" />
                                    <button type="submit" name="job_action_delete_btn">Delete</button>
                                </form>
                            </div>
                        </div>
                        <form method="post" class="job-action-edit hidden">
                            <input type="hidden" name="edit_course" value="<?= htmlspecialchars($job['course_code']) ?>" />
                            <input type="hidden" name="edit_section" value="<?= htmlspecialchars($job['section']) ?>" />
                            <div>
                                <label>Required CGPA</label>
                                <input type="text" name="edit_required_cgpa" value="<?= htmlspecialchars($job['min_cgpa']) ?>">
                            </div>
                            <div>
                                <label>Completed Credit</label>
                                <input type="text" name="edit_completed_credit" value="<?= htmlspecialchars($job['min_credit']) ?>">
                            </div>
                            <div>
                                <label>Obtained Grade</label>
                                <input type="text" name="edit_obtained_grade" value="<?= htmlspecialchars($job['min_grade']) ?>">
                            </div>
                            <div>
                                <label>Deadline</label>
                                <input type="date" name="edit_deadline" value="<?= htmlspecialchars($job['deadline']) ?>">
                            </div>
                            <input type="hidden" name="edit_job_role" value="<?= htmlspecialchars($job['job_role']) ?>" />
                            <button type="submit" name="update_job">Update</button>
                        </form>
                        <!-- error requirements -->
                        <?php if (isset($_SESSION['alert']) && $_SESSION['alert']['where'] === 'job-action-edit'): ?>
                            <div class="flash <?= htmlspecialchars($_SESSION['alert']['type']) ?>">
                                <?= $_SESSION['alert']['message'] ?>
                            </div>
                            <?php unset($_SESSION['alert']); ?>
                        <?php endif; ?>
                        <table class="job-action-view hidden">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>CGPA</th>
                                    <th>Credit</th>
                                    <th>Grade</th>
                                    <th>Resume</th>
                                    <th>Endorsements</th>
                                    <th>Experience</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($job['applicants'] as $app): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($app['student_id']) ?></td>
                                        <td><?= htmlspecialchars($app['cgpa']) ?></td>
                                        <td><?= htmlspecialchars($app['credits']) ?></td>
                                        <td><?= htmlspecialchars($app['obtained_grade']) ?></td>
                                        <td class="resume-actions">
                                            <a href="../uploads/resumes/<?= htmlspecialchars($app['resume']) ?>" target="_blank">View</a>
                                            <div style="height: 10px;"></div>
                                            <a href="../uploads/resumes/<?= htmlspecialchars($app['resume']) ?>" download>Download</a>
                                        </td>
                                        <td>
                                            <?php if (!empty($app['selected_endorsement_photos'])): ?>
                                                <?php foreach ($app['selected_endorsement_photos'] as $photo): ?>
                                                    <img src="../uploads/img/<?= htmlspecialchars($photo) ?>" width="40px" style="margin-right: 10px;" />
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?= $app['endorsement_count'] ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $app['has_experience'] ? '&#10003;' : '&#10005;' ?></td>
                                        <td><?= ucfirst(htmlspecialchars($app['status'])) ?></td>
                                        <td>
                                            <?php if ($app['status'] === 'pending'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($app['student_id']) ?>" />
                                                    <input type="hidden" name="course_code" value="<?= htmlspecialchars($job['course_code']) ?>" />
                                                    <input type="hidden" name="section" value="<?= htmlspecialchars($job['section']) ?>" />
                                                    <input type="hidden" name="job_role" value="<?= htmlspecialchars($job['job_role']) ?>" />
                                                    <button type="submit" name="applicant_accept">Accept</button>
                                                    <div style="height: 10px;"></div>
                                                    <button type="submit" name="applicant_reject">Reject</button>
                                                    <!-- error time conflict -->
                                                    <?php if (isset($_SESSION['alert']) && $_SESSION['alert']['where'] === 'job-action-accept'): ?>
                                                        <div class="flash <?= htmlspecialchars($_SESSION['alert']['type']) ?>">
                                                            <?= htmlspecialchars($_SESSION['alert']['message']) ?>
                                                        </div>
                                                        <?php unset($_SESSION['alert']); ?>
                                                    <?php endif; ?>
                                                </form>
                                            <?php else: ?>
                                                <p>Processed</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </article>
                <?php endforeach; ?>
            </section>
            <section class="hired-applicants">
                <h2>HIRED STUDENTS</h2>
                <div class="students-grid">
                    <?php foreach ($data['hired'] as $student): ?>
                        <div class="student-card">
                            <div class="student-card-info">
                                <div class="info-attributes">
                                    <p>Student</p>
                                    <p>Contact</p>
                                    <p>Course</p>
                                </div>
                                <div class="info-attributes">
                                    <p>&nbsp;:&nbsp;&nbsp;</p>
                                    <p>&nbsp;:&nbsp;&nbsp;</p>
                                    <p>&nbsp;:&nbsp;&nbsp;</p>
                                </div>
                                <div>
                                    <p><?= htmlspecialchars($student['name']) ?> (<?= htmlspecialchars($student['student_id']) ?>)</p>
                                    <p><?= htmlspecialchars($student['email']) ?></p>
                                    <p>(<?= htmlspecialchars($student['job_role']) ?>) <?= htmlspecialchars($student['course_code']) ?> - <?= htmlspecialchars($student['course_name']) ?> (<?= htmlspecialchars($student['section']) ?>)</p>
                                </div>
                            </div>
                            <div class="student-card-footer">
                                <img src="../uploads/img/<?= htmlspecialchars($student['photo'] ?? 'user.png') ?>">
                                <div class="student-card-actions">
                                    <form method="post">
                                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>" />
                                        <input type="hidden" name="course_code" value="<?= htmlspecialchars($student['course_code']) ?>" />
                                        <input type="hidden" name="section" value="<?= htmlspecialchars($student['section']) ?>" />
                                        <input type="hidden" name="job_role" value="<?= htmlspecialchars($student['job_role']) ?>" />
                                        <button type="submit" name="endorse" onclick="return confirm('Endorse applicant <?= htmlspecialchars($student['student_id']) ?>?')">Endorse</button>
                                        <button type="submit" name="terminate" onclick="return confirm('Accept termination request from <?= htmlspecialchars($student['student_id']) ?>?')">Terminate</button>
                                        <?php if (!empty($student['resign_request']) && $student['resign_request'] == 1): ?>
                                            <div class="flash">
                                                Resignation requested by the student
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
    <script src="script.js"></script>
</body>

</html>