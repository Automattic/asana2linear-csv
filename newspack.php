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
        'Title' => 'Name',
        'Description' => 'Notes',
        'Priority' => 'Priority [Newspack Product]',
        'Status' => 'Section/Column',
        'Assignee' => 'Assignee',
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
        'Assignee' => [
            'leogermani' => 'Leo Germani',
        ],
        'Section/Column' => [
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
     * Custom field handlers for special transformations.
     *
     * Each handler is a callable function that takes the input value and output row,
     * and returns the modified output row.
     */
    'field_handlers' => [
        'P2 or Slack thread [Newspack Product]' => function ( $input, $output, $transformer ) {
            // Get the index of the Description field
            $descriptionIndex = $transformer->getOutputFieldIndex('Description');

            // Append the P2 or Slack thread to the description
            if (! empty($input) ) {
                $output[ $descriptionIndex ] .= "\n\nP2 or Slack thread: " . $input;
            }

            return $output;
        },
    ],
];
