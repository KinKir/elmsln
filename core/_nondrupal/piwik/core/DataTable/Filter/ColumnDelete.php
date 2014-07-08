<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\DataTable\Filter;

use Piwik\DataTable;
use Piwik\DataTable\BaseFilter;

/**
 * Filter that will remove columns from a {@link DataTable} using either a blacklist,
 * whitelist or both.
 *
 * This filter is used to handle the **hideColumn** and **showColumn** query parameters.
 * 
 * **Basic usage example**
 * 
 *     $columnsToRemove = array('nb_hits', 'nb_pageviews');
 *     $dataTable->filter('ColumnDelete', array($columnsToRemove));
 * 
 *     $columnsToKeep = array('nb_visits');
 *     $dataTable->filter('ColumnDelete', array(array(), $columnsToKeep));
 * 
 * @api
 */
class ColumnDelete extends BaseFilter
{
    /**
     * The columns that should be removed from DataTable rows.
     *
     * @var array
     */
    private $columnsToRemove;

    /**
     * The columns that should be kept in DataTable rows. All other columns will be
     * removed. If a column is in $columnsToRemove and this variable, it will NOT be kept.
     *
     * @var array
     */
    private $columnsToKeep;

    /**
     * Hack: when specifying "showColumns", sometimes we'd like to also keep columns that "look" like a given column,
     * without manually specifying all these columns (which may not be possible if column names are generated dynamically)
     *
     * Column will be kept, if they match any name in the $columnsToKeep, or if they look like anyColumnToKeep__anythingHere
     */
    const APPEND_TO_COLUMN_NAME_TO_KEEP = '__';

    /**
     * Delete the column, only if the value was zero
     *
     * @var bool
     */
    private $deleteIfZeroOnly;

    /**
     * Constructor.
     *
     * @param DataTable $table The DataTable instance that will eventually be filtered.
     * @param array|string $columnsToRemove An array of column names or a comma-separated list of
     *                                      column names. These columns will be removed.
     * @param array|string $columnsToKeep An array of column names that should be kept or a
     *                                    comma-separated list of column names. Columns not in
     *                                    this list will be removed.
     * @param bool $deleteIfZeroOnly If true, columns will be removed only if their value is 0.
     */
    public function __construct($table, $columnsToRemove, $columnsToKeep = array(), $deleteIfZeroOnly = false)
    {
        parent::__construct($table);

        if (is_string($columnsToRemove)) {
            $columnsToRemove = $columnsToRemove == '' ? array() : explode(',', $columnsToRemove);
        }

        if (is_string($columnsToKeep)) {
            $columnsToKeep = $columnsToKeep == '' ? array() : explode(',', $columnsToKeep);
        }

        $this->columnsToRemove = $columnsToRemove;
        $this->columnsToKeep = array_flip($columnsToKeep); // flip so we can use isset instead of in_array
        $this->deleteIfZeroOnly = $deleteIfZeroOnly;
    }

    /**
     * See {@link ColumnDelete}.
     *
     * @param DataTable $table
     */
    public function filter($table)
    {
        // always do recursive filter
        $this->enableRecursive(true);
        $recurse = false; // only recurse if there are columns to remove/keep

        // remove columns specified in $this->columnsToRemove
        if (!empty($this->columnsToRemove)) {
            foreach ($table->getRows() as $row) {
                foreach ($this->columnsToRemove as $column) {
                    if ($this->deleteIfZeroOnly) {
                        $value = $row->getColumn($column);
                        if ($value === false || !empty($value)) {
                            continue;
                        }
                    }
                    $row->deleteColumn($column);
                }
            }

            $recurse = true;
        }

        // remove columns not specified in $columnsToKeep
        if (!empty($this->columnsToKeep)) {
            foreach ($table->getRows() as $row) {
                foreach ($row->getColumns() as $name => $value) {

                    $keep = false;
                    // @see self::APPEND_TO_COLUMN_NAME_TO_KEEP
                    foreach ($this->columnsToKeep as $nameKeep => $true) {
                        if (strpos($name, $nameKeep . self::APPEND_TO_COLUMN_NAME_TO_KEEP) === 0) {
                            $keep = true;
                        }
                    }

                    if (!$keep
                        && $name != 'label' // label cannot be removed via whitelisting
                        && !isset($this->columnsToKeep[$name])
                    ) {
                        $row->deleteColumn($name);
                    }
                }
            }

            $recurse = true;
        }

        // recurse
        if ($recurse) {
            foreach ($table->getRows() as $row) {
                $this->filterSubTable($row);
            }
        }
    }
}
