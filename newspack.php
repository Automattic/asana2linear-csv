<?php
/**
 * Newspack configuration for Asana to Linear CSV transformer
 *
 * @package AsanaToLinear
 */

return [
    /**
     * Base mapping from Asana to Linear.
     */
    'base_mapping' => [
        // Linear => Asana.
        'Title' => 'Task Name',
        'Description' => 'Description',
        'Priority' => 'Priority [Newspack Product]',
        'Status' => 'Sections',
        'Assignee' => 'Assignee',
        'Created' => 'Created At',
        'Completed' => 'Completed At',
        'Labels' => '',
    ],

    /**
     * Field value mappings for translation between systems.
     *
     * The key is the field name in Asana, the value is an array of key-value pairs
     * of the Asana value on the left and the Linear value on the right.
     */
    'fields_mapping' => [
        'Priority [Newspack Product]' => [
            'Critical' => 'Urgent',
            'Backlog: When possible' => 'No priority',
            'High' => 'High',
            'Medium' => 'Medium',
            'Low' => 'Low',
        ],
        /**
         * Looks like with the export from Bridge24, the Assignee field already matches the Linear user name.
         * It's the full name, so we don't need to do anything, except for Raz.
         */
        'Assignee' => [
            'Rasmy Nguyen' => 'rasmy.nguyen@a8c.com',
        ],
        'Sections' => [
            // Product Engineering Inbox.
            'Inbox (NEW TASKS HERE)' => 'Triage',
            'In triage / discussion' => 'Todo',
            'Triaged / Ready to start' => 'Triaged / Ready to start',
            'In progress' => 'In progress',
            'On hold / blocked' => 'On hold / Blocked',
            'Review / Testing / Feedback' => 'In Review',
            'Merged / Awaiting release' => 'Merged / Awaiting release',
            'Released and deployed' => 'Done',
            "Won't Fix" => 'Won\'t Fix',
            'Complete' => 'Done',

            // Simple project template used in a few projects.
            'Backlog' => 'Todo',
            'In Progress' => 'In progress',
            'Review' => 'In Review',
            'Blocked/On Hold' => 'On hold / Blocked',
            'Done' => 'Done',
        ],
    ],

    /**
     * Mappings for Asana fields that will be transformed into Linear labels.
     *
     * The key is the field name in Asana, the value is an array of key-value pairs
     * of the Asana value on the left and the Linear value on the right.
     */
    'labels_mapping' => [
        'Level of Effort [Newspack Product]' => [
            'Needs Discovery' => 'Needs Discovery',
            'Launch Blocking' => 'Launch Blocking',
            'Small' => 'Small Effort',
            'Medium' => 'Medium Effort',
            'Large' => 'Large Effort',
            'Extra-Large' => 'Extra Large Effort',
        ],
        'Inbox task type' => [
            'Bug' => 'Bug',
            'From Product Feedback' => 'New Feature',
            'Support' => 'Question (new)',
            'Maintenance & Worfklow' => 'Task (new)',
            'Styling' => 'Task (new)',
        ],
        'Impact' => [
            '1-Launch Blocking' => 'High Impact',
            '2-High' => 'High Impact',
            '3-Medium' => 'Medium Impact',
            '4-Low' => 'Low Impact',
            '5-Maintenance high' => 'High Impact',
            '6-Maintenance low' => 'Low Impact',
        ],
    ],

    /**
     * Fields that will be appended to the description.
     */
    'append_to_description' => [
        'P2 or Slack thread [Newspack Product]',
        'Task URL',
        'Comments',
    ],

    /**
     * Custom field handlers for special transformations.
     *
     * Each handler is a callable function that takes the input value and output row,
     * and returns the modified output row.
     */
    'field_handlers' => [
        'Completed' => function ( $input, $output, $transformer ) {
            // If the Completed field is set to 1, force the Status field to "Done".
            if ($input == '1') {
                $output[ $transformer->getOutputFieldIndex('Status') ] = 'Done';
            }
            return $output;
        },
    ],
];
