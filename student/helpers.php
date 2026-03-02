<?php
// Escape HTML to prevent XSS
function e($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// For form <select> fields: sets the selected option
function selected($value, $current)
{
    return $value === $current ? 'selected' : '';
}

// For form <input type="checkbox">: sets checked attribute
function checked($name, $data)
{
    return !empty($data[$name]) ? 'checked' : '';
}

// Dropdown configuration generator for job filters
function get_job_filter_dropdowns($time_slots = [])
{
    $dropdowns = [
        'select_role' => [
            'label' => 'Job Role',
            'default' => 'select',
            'options' => [
                'select' => 'Select',
                'UA' => 'UA',
                'GRADER' => 'Grader'
            ]
        ],
        'select_department' => [
            'label' => 'Department',
            'default' => 'select',
            'options' => [
                'select' => 'Select',
                'CSE' => 'CSE',
                'DS' => 'DS',
                'EEE' => 'EEE'
            ]
        ],
        'select_day' => [
            'label' => 'Day',
            'default' => 'select',
            'options' => [
                'select' => 'Select',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday',
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday'
            ]
        ],
        'select_time' => [
            'label' => 'Time Slot',
            'default' => 'select',
            'options' => ['select' => 'Select']
        ]
    ];

    if (!empty($time_slots)) {
        foreach ($time_slots as $slot) {
            $dropdowns['select_time']['options'][$slot] = $slot;
        }
    } else {
        $dropdowns['select_time']['options'] += [
            '08:30-11:00' => '08:30–11:00',
            '11:10-13:40' => '11:10–13:40',
            '14:00-16:30' => '14:00–16:30'
        ];
    }

    return $dropdowns;
}
