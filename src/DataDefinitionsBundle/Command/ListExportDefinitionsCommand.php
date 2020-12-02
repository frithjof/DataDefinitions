<?php
/**
 * Data Definitions.
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2016-2019 w-vision AG (https://www.w-vision.ch)
 * @license    https://github.com/w-vision/DataDefinitions/blob/master/gpl-3.0.txt GNU General Public License version 3 (GPLv3)
 */

namespace Wvision\Bundle\DataDefinitionsBundle\Command;

use CoreShop\Component\Resource\Repository\RepositoryInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wvision\Bundle\DataDefinitionsBundle\Model\ExportDefinitionInterface;

final class ListExportDefinitionsCommand extends AbstractCommand
{
    protected $repository;

    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('data-definitions:list:exports')
            ->setDescription('List all Export Definitions.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> lists all Data Definitions for Exports.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exportDefinitions = $this->repository->findAll();

        $data = [];

        /** @var ExportDefinitionInterface $definition */
        foreach ($exportDefinitions as $definition) {
            $data[] = [
                $definition->getId(),
                $definition->getName(),
                $definition->getProvider(),
            ];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['ID', 'Name', 'Provider'])
            ->setRows($data);
        $table->render();

        return 0;
    }
}

