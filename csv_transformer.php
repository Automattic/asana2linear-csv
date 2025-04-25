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
    private $_base_mapping = [];
    private $_fields_mapping = [];
    private $_labels_mapping = [];
    private $_field_handlers = [];
    private $_config_file;

    /**
     * Constructor
     *
     * @param string $inputFile  Path to the input CSV file.
     * @param string $configFile Path to the configuration file.
     */
    public function __construct( $inputFile, $configFile )
    {
        if (! file_exists($inputFile) ) {
            throw new Exception("Input file does not exist: $inputFile");
        }

        $this->_inputFile = $inputFile;
        $this->_config_file = $configFile;

        $this->_loadConfig();
        $this->_parseHeaders();
    }

    /**
     * Load configuration from the specified config file
     *
     * @return void
     */
    private function _loadConfig()
    {
        if (! file_exists($this->_config_file) ) {
            throw new Exception("Configuration file does not exist: {$this->_config_file}");
        }

        $config = include $this->_config_file;

        if (! is_array($config) ) {
            throw new Exception('Invalid configuration format. Expected an array.');
        }

        // Load mappings from config
        $this->_base_mapping = $config['base_mapping'] ?? [];
        $this->_fields_mapping = $config['fields_mapping'] ?? [];
        $this->_labels_mapping = $config['labels_mapping'] ?? [];
        $this->_field_handlers = $config['field_handlers'] ?? [];

        // Validate required mappings
        if (empty($this->_base_mapping) ) {
            throw new Exception('Base mapping is required in configuration.');
        }
    }

    /**
     * Parse the CSV headers and store field names and indexes
     *
     * @return void
     */
    private function _parseHeaders()
    {
        $handle = fopen($this->_inputFile, 'r');
        if ($handle === false ) {
            throw new Exception("Failed to open file: {$this->_inputFile}");
        }

        // Read the first line to get headers
        $headers = fgetcsv($handle);
        if ($headers === false ) {
            fclose($handle);
            throw new Exception('Failed to read headers from CSV file');
        }

        // Store headers and their indexes
        foreach ( $headers as $index => $header ) {
            $this->_headers[] = $header;
            $this->_headerIndexes[ $header ] = $index;
        }

        fclose($handle);
    }

    /**
     * Get the headers from the CSV file
     *
     * @return array The headers
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * Get the header indexes
     *
     * @return array The header indexes
     */
    public function getHeaderIndexes()
    {
        return $this->_headerIndexes;
    }

    /**
     * Get a value from a CSV line by field name
     *
     * @param array  $line       The CSV line as an array.
     * @param string $field_name The field name to get the value for.
     *
     * @return string The value or empty string if field not found.
     */
    private function get_value_by_field_name( $line, $field_name )
    {
        if (empty($field_name) ) {
            return '';
        }

        if (isset($this->_headerIndexes[ $field_name ]) ) {
            $index = $this->_headerIndexes[ $field_name ];
            return $line[ $index ];
        }

        return '';
    }

    /**
     * Get the index of a field in the output CSV
     *
     * @param string $field_name The field name to get the index for.
     *
     * @return int|null The index of the field or null if not found.
     */
    public function getOutputFieldIndex( $field_name )
    {
        $keys  = array_keys($this->_base_mapping);
        $index = array_search($field_name, $keys);

        return $index !== false ? $index : null;
    }

    /**
     * Generic method to handle adding a label to the output
     *
     * @param string $input      The input value.
     * @param array  $output     The output line.
     * @param array  $mapping    The mapping to apply.
     * @param string $field_name The field name for error messages.
     *
     * @return array The transformed line.
     */
    private function _handleLabel( $input, $output, $mapping, $field_name )
    {
        if (isset($mapping[ $input ]) ) {
            $input = $mapping[ $input ];
        } else {
            if (! empty($input) ) {
                echo "Unknown {$field_name}: $input\n";
            }
            $input = '';
        }

        if (empty($input) ) {
            return $output;
        }

        $labelIndex = $this->getOutputFieldIndex('Labels');
        if (! empty($output[ $labelIndex ]) ) {
            $output[ $labelIndex ] .= ', ';
        }

        $output[ $labelIndex ] .= $input;
        return $output;
    }

    /**
     * Handle field mapping for direct field-to-field transformations
     *
     * @param string $input      The input value.
     * @param array  $output     The output line.
     * @param array  $mapping    The mapping to apply.
     * @param string $field_name The field name to update.
     *
     * @return array The transformed line.
     */
    private function _handleFieldMapping( $input, $output, $mapping, $field_name )
    {
        $fieldIndex = $this->getOutputFieldIndex($field_name);

        if (empty($input) ) {
            $output[ $fieldIndex ] = '';
            return $output;
        }

        if (isset($mapping[ $input ]) ) {
            $output[ $fieldIndex ] = $mapping[ $input ];
        } else {
            echo "Unknown {$field_name}: $input\n";
            $output[ $fieldIndex ] = $input; // Keep original value if no mapping exists
        }

        return $output;
    }

    /**
     * Transform the CSV file based on the mapping
     *
     * @param string $outputFile Path to the output CSV file.
     *
     * @return void
     */
    public function transform( $outputFile )
    {
        $inputHandle = fopen($this->_inputFile, 'r');
        if ($inputHandle === false ) {
            throw new Exception("Failed to open input file: {$this->_inputFile}");
        }

        $outputHandle = fopen($outputFile, 'w');
        if ($outputHandle === false ) {
            fclose($inputHandle);
            throw new Exception("Failed to open output file: {$outputFile}");
        }

        // Skip the header row in the input file
        fgetcsv($inputHandle);

        // Write the header row to the output file
        fputcsv($outputHandle, array_keys($this->_base_mapping));

        // Process each row
        while ( ( $row = fgetcsv($inputHandle) ) !== false ) {
            $outputRow = [];

            foreach ( $this->_base_mapping as $outputField => $inputField ) {
                $outputRow[] = $this->get_value_by_field_name($row, $inputField);
            }

            // Process field mappings
            foreach ( $this->_fields_mapping as $inputField => $mapping ) {
                $input     = $this->get_value_by_field_name($row, $inputField);
                $fieldName = array_search($inputField, $this->_base_mapping) ?: $inputField;
                $outputRow = $this->_handleFieldMapping(
                    $input,
                    $outputRow,
                    $mapping,
                    $fieldName
                );
            }

            // Process special field handlers
            foreach ( $this->_field_handlers as $inputField => $handler ) {
                $input = $this->get_value_by_field_name($row, $inputField);

                // Check if handler is a callable function from config
                if (is_callable($handler) ) {
                    $outputRow = $handler($input, $outputRow, $this);
                } else if (method_exists($this, $handler) ) {
                    // Fallback to class method if defined
                    $outputRow = $this->$handler($input, $outputRow);
                } else {
                    echo "Warning: Handler '$handler' for field '$inputField' not found.\n";
                }
            }

            // Process label mappings
            foreach ( $this->_labels_mapping as $inputField => $mapping ) {
                $input     = $this->get_value_by_field_name($row, $inputField);
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
if (PHP_SAPI === 'cli' ) {
    if ($argc < 4 ) {
        echo "Usage: php csv_transformer.php <input_file.csv> <output_file.csv> <config_file.php>\n";
        exit(1);
    }

    $inputFile  = $argv[1];
    $outputFile = $argv[2];
    $configFile = $argv[3];

    try {
        $transformer = new CSVTransformer($inputFile, $configFile);

        echo "Transforming CSV file...\n";
        echo "Using configuration: {$configFile}\n";
        $transformer->transform($outputFile);
        echo "Transformation complete. Output written to: {$outputFile}\n";
    } catch ( Exception $e ) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
