<?php


namespace Henrique\Salsimport\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Command
{

    const NAME_ARGUMENT = "name";
    const NAME_OPTION = "option";
    var $importHelper;


    public function __construct(
        \Henrique\Salsimport\Helper\Import $_importHelper
    ){
        $this->importHelper = $_importHelper;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->importHelper->importStart($output);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("Henrique_salsimport:import");
        $this->setDescription("Import all files from directory");
        $this->setDefinition([
            new InputArgument(self::NAME_ARGUMENT, InputArgument::OPTIONAL, "Name"),
            new InputOption(self::NAME_OPTION, "-a", InputOption::VALUE_NONE, "Option functionality")
        ]);
        parent::configure();
    }
}
