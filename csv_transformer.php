<?php
/**
 * CSV Transformer
 *
 * A utility for transforming CSV files from one format to another
 * based on predefined field mappings.
 *
 * @package AsanaToLinear
 */

/**
 * Class for transforming CSV files from one format to another
 */
class CSVTransformer
{
    private $_inputFile;
    private $_headers = [];
    private $_headerIndexes = [];

    /**
     * Base mapping from Asana to Linear.
     *
     * @var array
     */
    private $_base_mapping = [
        // Linear => Asana.
        'Title' => 'Name',
        'Description' => 'Notes',
        'Priority' => 'Priority [Newspack Product]',
        'Status' => 'Section/Column',
        'Assignee' => 'Assignee',
        'Labels' => '',
    ];

    /**
     * These are mappings for the field values, when they need to be translated.
     *
     * The key is the field name in Asana, the value is an array of key-value pairs
     * of the Asana value on the left and the Linear value on the right.
     *
     * @var array
     */
    private $_fields_mapping = [
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
    ];

    /**
     * These are mappings for the Asana fields that will be transformed into labels.
     *
     * The key is the field name in Asana, the value is an array of key-value pairs
     * of the Asana value on the left and the Linear value on the right.
     *
     * Note that we don't need to specify which label the value is being mapped to,
     * because label values are unique in Linear across all labels.
     *
     * @var array
     */
    private $_labels_mapping = [
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
        ]
    ];

    /**
     * These are special handlers for certain fields that need to be transformed in a special way.
     *
     * The key is the field name in Asana, the value is the method name to call to handle the field.
     *
     * @var array
     */
    private $_field_handlers = [
        'P2 or Slack thread [Newspack Product]' => 'handle_p2_or_slack_thread',
    ];

    /**
     * Constructor
     *
     * @param string $inputFile Path to the input CSV file
     */
    public function __construct(string $inputFile)
    {
        if (!file_exists($inputFile)) {
            throw new Exception("Input file does not exist: $inputFile");
        }

        $this->_inputFile = $inputFile;
        $this->_parseHeaders();
    }

    /**
     * Parse the CSV headers and store field names and indexes
     *
     * @return void
     */
    private function _parseHeaders(): void
    {
        $handle = fopen($this->_inputFile, 'r');
        if ($handle === false) {
            throw new Exception("Failed to open file: {$this->_inputFile}");
        }

        // Read the first line to get headers
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new Exception("Failed to read headers from CSV file");
        }

        // Store headers and their indexes
        foreach ($headers as $index => $header) {
            $this->_headers[] = $header;
            $this->_headerIndexes[$header] = $index;
        }

        fclose($handle);
    }

    /**
     * Get the headers from the CSV file
     *
     * @return array The headers
     */
    public function getHeaders(): array
    {
        return $this->_headers;
    }

    /**
     * Get the header indexes
     *
     * @return array The header indexes
     */
    public function getHeaderIndexes(): array
    {
        return $this->_headerIndexes;
    }

    /**
     * Get a value from a CSV line by field name
     *
     * @param  array  $line       The CSV line as an array
     * @param  string $field_name The field name to get the value for
     * @return string The value or empty string if field not found
     */
    private function get_value_by_field_name(array $line, string $field_name): string
    {
        if (empty($field_name)) {
            return '';
        }

        if (isset($this->_headerIndexes[$field_name])) {
            $index = $this->_headerIndexes[$field_name];
            return $line[$index];
        }

        return '';
    }

    /**
     * Handle P2 or Slack thread Label and adds its content to the end of the Description field.
     *
     * @param  string $input  The input value
     * @param  array  $output The output line
     * @return array The transformed line
     */
    public function handle_p2_or_slack_thread( $input, $output )
    {
        $output[ $this->_getOutputFieldIndex('Description') ] .= "\n\nP2 or Slack thread:" . $input;
        return $output;
    }

    /**
     * Generic method to handle adding a label to the output
     *
     * @param  string $input      The input value
     * @param  array  $output     The output line
     * @param  array  $mapping    The mapping to apply
     * @param  string $field_name The field name for error messages
     * @return array  The transformed line
     */
    private function _handleLabel(string $input, array $output, array $mapping, string $field_name): array
    {
        if (isset($mapping[$input])) {
            $input = $mapping[$input];
        } else {
            if (!empty($input)) {
                echo "Unknown {$field_name}: $input\n";
            }
            $input = '';
        }

        if (empty($input)) {
            return $output;
        }

        $labelIndex = $this->_getOutputFieldIndex('Labels');
        if (!empty($output[$labelIndex])) {
            $output[$labelIndex] .= ', ';
        }

        $output[$labelIndex] .= $input;
        return $output;
    }

    /**
     * Handle field mapping for direct field-to-field transformations
     *
     * @param string $input      The input value
     * @param array  $output     The output line
     * @param array  $mapping    The mapping to apply
     * @param string $field_name The field name to update
     *
     * @return array  The transformed line
     */
    private function _handleFieldMapping(string $input, array $output, array $mapping, string $field_name): array
    {
        $fieldIndex = $this->_getOutputFieldIndex($field_name);

        if (empty($input)) {
            $output[$fieldIndex] = '';
            return $output;
        }

        if (isset($mapping[$input])) {
            $output[$fieldIndex] = $mapping[$input];
        } else {
            echo "Unknown {$field_name}: $input\n";
            $output[$fieldIndex] = $input; // Keep original value if no mapping exists
        }

        return $output;
    }

    /**
     * Get the index of a field in the output CSV
     *
     * @param  string $field_name The field name to get the index for
     * @return int|null The index of the field or null if not found
     */
    private function _getOutputFieldIndex(string $field_name): ?int
    {
        $keys = array_keys($this->_base_mapping);
        $index = array_search($field_name, $keys);

        return $index !== false ? $index : null;
    }

    /**
     * Transform the CSV file based on the mapping
     *
     * @param  string $outputFile Path to the output CSV file
     * @return void
     */
    public function transform(string $outputFile): void
    {
        $inputHandle = fopen($this->_inputFile, 'r');
        if ($inputHandle === false) {
            throw new Exception("Failed to open input file: {$this->_inputFile}");
        }

        $outputHandle = fopen($outputFile, 'w');
        if ($outputHandle === false) {
            fclose($inputHandle);
            throw new Exception("Failed to open output file: {$outputFile}");
        }

        // Skip the header row in the input file
        fgetcsv($inputHandle);

        // Write the header row to the output file
        fputcsv($outputHandle, array_keys($this->_base_mapping));

        // Process each row
        while (($row = fgetcsv($inputHandle)) !== false) {
            $outputRow = [];

            foreach ($this->_base_mapping as $outputField => $inputField) {
                $outputRow[] = $this->get_value_by_field_name($row, $inputField);
            }

            // Process field mappings
            foreach ($this->_fields_mapping as $inputField => $mapping) {
                $input = $this->get_value_by_field_name($row, $inputField);
                $fieldName = array_search($inputField, $this->_base_mapping) ?: $inputField;
                $outputRow = $this->_handleFieldMapping(
                    $input,
                    $outputRow,
                    $mapping,
                    $fieldName
                );
            }

            // Process special field handlers
            foreach ($this->_field_handlers as $inputField => $handler) {
                $outputRow = $this->$handler($this->get_value_by_field_name($row, $inputField), $outputRow);
            }

            // Process label mappings
            foreach ($this->_labels_mapping as $inputField => $mapping) {
                $input = $this->get_value_by_field_name($row, $inputField);
                $outputRow = $this->_handleLabel(
                    $input,
                    $outputRow,
                    $mapping,
                    $inputField
                );
            }

            fputcsv($outputHandle, $outputRow);
        }

        fclose($inputHandle);
        fclose($outputHandle);
    }
}

// CLI script execution
if (PHP_SAPI === 'cli') {
    if ($argc < 3) {
        echo "Usage: php csv_transformer.php <input_file.csv> <output_file.csv>\n";
        exit(1);
    }

    $inputFile = $argv[1];
    $outputFile = $argv[2];

    try {
        $transformer = new CSVTransformer($inputFile);

        echo "Transforming CSV file...\n";
        $transformer->transform($outputFile);
        echo "Transformation complete. Output written to: {$outputFile}\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
