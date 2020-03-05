<?php

namespace Henrique\Salsimport\Cron;

/**
 * Class Import
 * @package Henrique\Salsimport\Cron
 */
class Import
{
    /**
     * @var \Henrique\Salsimport\Model\Import
     */
    private $import;

    public function __construct(
        \Henrique\Salsimport\Model\Import $import
    ) {
        $this->import = $import;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->import->import();;
    }
}
