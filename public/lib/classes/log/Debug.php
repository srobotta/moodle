<?php

/**
 * Very simple debug logger.
 *
 * @package    core
 * @copyright  2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\log;

defined('MOODLE_INTERNAL') || die();

class Debug {
    protected static $instance = null;

    /**
     * Get the singleton instance of the class.
     */
    public static function get_instance(): Debug {
        if (self::$instance === null) {
            self::$instance = new Debug();
        }
        return self::$instance;
    }

    /**
     * Get line prefix for log message with date and pid.
     *
     * @return string
     */
    protected function get_line_prefix(): string {
        return date('Y-m-d H:i:s') . ' [' . getmypid() . '] ';
    }

    /**
     * Get the log file with path.
     *
     * @return string
     */
    protected function get_log_file(): string {
        global $CFG;
        return $CFG->dataroot . '/debug-' . date('Y-m-d') . '.log';
    }

    /**
     * Write a log line. Replace any arguments in the message with the values.
     * Values are json encoded if they are objects or arrays.
     *
     * @param string $message
     * @param mixed ...$args
     * @return void
     */
    public function log(string $message, ...$args): void {
        foreach ($args as $key => $value) {
            if (is_object($value)) {
                $value = get_class($value) . ' = ' . json_encode($value);
            } elseif (is_array($value)) {
                $value = json_encode($value);
            }
            $message = str_replace('{' . ($key + 1) . '}', $value, $message);
        }
        try {
            file_put_contents($this->get_log_file(), $this->get_line_prefix() . $message . PHP_EOL, FILE_APPEND);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }       
    }
}
