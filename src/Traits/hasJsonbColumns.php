<?php

namespace Traits;

use fGrammar;
use fORM;

trait hasJsonbColumns
{
    /**
     * The columns that will be jsonb type columns.
     *
     * @var array
     */
    protected $jsonbColumns = [];

    public function initJsonbColumns()
    {
        foreach ($this->jsonbColumns as $column) {
            fORM::registerActiveRecordMethod(
                $this,
                'get'.fGrammar::camelize($column, true),
                function ($object, &$values, &$old, &$related, &$cache, $method, $parameters) use ($column) {
                    if ($values[$column] && is_string($values[$column])) {
                        return json_decode($values[$column], true);
                    }

                    return $values[$column][0] ?? [];
                }
            );

            fORM::registerActiveRecordMethod(
                $this,
                'set'.fGrammar::camelize($column, true),
                function ($object, &$values, &$old, &$related, &$cache, $method, $parameters) use ($column) {
                    if (is_array($parameters) && !count($parameters)) {
                        $parameters = '{}';
                    }

                    if (is_array($parameters)) {
                        $parameters = json_encode($parameters[0], JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                    }

                    $values[$column] = $parameters;
                }
            );
        }
    }
}
