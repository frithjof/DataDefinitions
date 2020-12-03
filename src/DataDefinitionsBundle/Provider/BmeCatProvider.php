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

namespace Wvision\Bundle\DataDefinitionsBundle\Provider;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use Wvision\Bundle\DataDefinitionsBundle\Model\ExportDefinitionInterface;
use Wvision\Bundle\DataDefinitionsBundle\Model\ImportDefinitionInterface;
use Wvision\Bundle\DataDefinitionsBundle\Model\ImportMapping\FromColumn;
use Wvision\Bundle\DataDefinitionsBundle\ProcessManager\ArtifactGenerationProviderInterface;
use Wvision\Bundle\DataDefinitionsBundle\ProcessManager\ArtifactProviderTrait;

class BmeCatProvider extends AbstractFileProvider implements ImportProviderInterface, ExportProviderInterface, ArtifactGenerationProviderInterface
{
    use ArtifactProviderTrait;

    /** @var \XMLWriter */
    private $writer;

    /** @var string */
    private $exportPath;

    /** @var int */
    private $exportCounter = 0;

    protected $configuration;

    /**
     * @param $xml
     * @param $xpath
     * @return mixed
     */
    protected function convertXmlToArray($xml, $xpath , $mode ='categories' , $classification_store_id = 1 )
    {
        $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($mode === 'categories'){

            $xml = $xml->xpath('//CATALOG_STRUCTURE');
        }
        elseif ($mode === 'articles'){
            $xml = $xml->xpath('//ARTICLE');
        }
        else{
            $xml = $xml->xpath($xpath);
        }
        $json = json_encode($xml);
        $array = json_decode($json, true);
        $features_for_classification_store = array();
        $flattened_attributes = array();
        foreach ($array as &$arrayEntry) {
            if($mode ==='articles'){
                // flatten keywords
                $keywords = implode(',',$arrayEntry['ARTICLE_DETAILS']['KEYWORD']);
                $arrayEntry['ARTICLE_DETAILS']['KEYWORD'] = $keywords;
                foreach ($arrayEntry['ARTICLE_FEATURES'] as $feature_block){
                    if(isset($feature_block['REFERENCE_FEATURE_SYSTEM_NAME'])){
                        //collect features and add classification stores
                        $classification_system = $feature_block['REFERENCE_FEATURE_SYSTEM_NAME'];
                        $features_for_classification_store[$classification_system] = array();
                    }
                    elseif(isset($feature_block['FEATURE'])){
                        foreach ($feature_block['FEATURE'] as $feature){
                            $features_for_classification_store[$classification_system][] =  $feature['FNAME'];
                            $flattened_attributes[$feature['FNAME']] = $feature['FVALUE'];
                        }

                    }
                    $arrayEntry['REFERENCE_FEATURE_SYSTEM_NAME'] = $classification_system;

                }
                $arrayEntry['features'] = $flattened_attributes;
                unset($arrayEntry['ARTICLE_FEATURES']);

            }
            $arrayEntry = $this->flattenBranch($arrayEntry);

        }
        if($mode ==='articles'){
            //create classification stores and keys that are needed
            foreach ($features_for_classification_store as $store_name => $keys){

                // Group
                $groupConfig =  \Pimcore\Model\DataObject\Classificationstore\GroupConfig::getByName($store_name, $classification_store_id);
                if(!is_object($groupConfig)){
                    $groupConfig = new \Pimcore\Model\DataObject\Classificationstore\GroupConfig();
                    $groupConfig->setName($store_name);
                    $groupConfig->setDescription($store_name);
                    $groupConfig->setStoreId($classification_store_id);
                    $groupConfig->save();
                }
                foreach ($keys as $key){
                    $keyConfig = \Pimcore\Model\DataObject\Classificationstore\KeyConfig::getByName($key, $classification_store_id);
                    if(!is_object($keyConfig)){

                        $definition = new \Pimcore\Model\DataObject\ClassDefinition\Data\Input();
                        $definition->setName($key);
                        $definition->setTitle($key);
                        $keyConfig = new \Pimcore\Model\DataObject\Classificationstore\KeyConfig();
                        $keyConfig->setName($key);
                        $keyConfig->setDescription($key);
                        $keyConfig->setEnabled(true);
                        $keyConfig->setType($definition->getFieldtype());
                        $keyConfig->setDefinition(json_encode($definition)); // The definition is used in object editor to render fields
                        $keyConfig->setStoreId($classification_store_id);
                        $keyConfig->save();
                    }
                    $keyConfig->setEnabled(true);
                    $keyConfig->save();

                    $rel = new KeyGroupRelation();
                    $rel->setKeyId($keyConfig->getId());
                    $rel->setGroupId($groupConfig->getId());
                    $rel->save();


                }

            }
        }

        return $array;
    }
    /*
     * @param $array
     * @return mixed
     */
    protected function flattenBranch($array , $topkey = '' , $depth = 0){
        //a failsafe for recursion
        if($depth > 100) return $array;
        $flattened_branch = array();
        foreach ($array as $key => $element){
            $subkey = ($topkey === '') ? $key : $topkey.'_'.$key;
            if(!is_array($element)){
                $flattened_branch[$subkey] = $element;
            }
            else{
                $flattened_branch = array_merge($flattened_branch,$this->flattenBranch($element, $subkey , $depth +1));
            }
        }
        return $flattened_branch;
    }

    /**
     * {@inheritdoc}
     */
    public function testData(array $configuration): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns(array $configuration)
    {
        $exampleFile = Asset::getById($configuration['exampleFile']);
        $rows = $this->convertXmlToArray($exampleFile->getData(), $configuration['exampleXPath'] , $configuration['sourcemode'] , $configuration['classificationStore']);
        $rows = $rows[0];

        $returnHeaders = [];

        if (\count($rows) > 0) {
            $firstRow = $rows;

            foreach ($firstRow as $key => $val) {
                $headerObj = new FromColumn();
                $headerObj->setIdentifier($key);
                $headerObj->setLabel($key);

                $returnHeaders[] = $headerObj;
            }
        }

        return $returnHeaders;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(array $configuration, ImportDefinitionInterface $definition, array $params, $filter = null)
    {
        $file = $this->getFile($params['file']);
        $xml = file_get_contents($file);

        return $this->convertXmlToArray($xml, $configuration['xPath'], $configuration['sourcemode'] , $configuration['classificationStore']);
    }

    public function addExportData(array $data, array $configuration, ExportDefinitionInterface $definition, array $params): void
    {
        $writer = $this->getXMLWriter();

        $writer->startElement('object');
        $this->serializeCollection($writer, $data);
        $writer->endElement();

        $this->exportCounter++;
        if ($this->exportCounter >= 50) {
            $this->flush($writer);
            $this->exportCounter = 0;
        }
    }

    public function exportData(array $configuration, ExportDefinitionInterface $definition, array $params): void
    {
        $writer = $this->getXMLWriter();

        // </root>
        $writer->endElement();
        $this->flush($writer);

        // XSLT transformation support
        if (array_key_exists('xsltPath', $configuration) && $configuration['xsltPath']) {
            $dataPath = $this->getExportPath();
            $xstlPath = $file = sprintf('%s/%s', PIMCORE_ASSET_DIRECTORY, ltrim($configuration['xsltPath'], '/'));

            if (!file_exists($xstlPath)) {
                throw new RuntimeException(sprintf('Passed XSLT file "%1$s" not found', $configuration['xsltPath']));
            }

            if (!is_readable($xstlPath)) {
                throw new RuntimeException(sprintf('Passed XSLT file "%1$s" not readable', $configuration['xsltPath']));
            }

            $this->exportPath = tempnam(sys_get_temp_dir(), 'xml_export_xslt_transformation');
            $cmd = sprintf('xsltproc -v %1$s %2$s > %3$s', $xstlPath, $dataPath, $this->getExportPath());
            $process = new Process($cmd);
            $process->setTimeout(null);
            $process->run();

            if (false === $process->isSuccessful()) {
                throw new RuntimeException($process->getErrorOutput());
            }
        }

        if (!array_key_exists('file', $params)) {
            return;
        }

        $file = $this->getFile($params['file']);
        rename($this->getExportPath(), $file);
    }

    public function provideArtifactStream($configuration, ExportDefinitionInterface $definition, $params)
    {
        return fopen($this->getExportPath(), 'rb');
    }

    private function getXMLWriter(): \XMLWriter
    {
        if (null === $this->writer) {
            $this->writer = new \XMLWriter();
            $this->writer->openMemory();
            $this->writer->setIndent(true);
            $this->writer->startDocument('1.0', 'UTF-8');

            // <root>
            $this->writer->startElement('export');
        }

        return $this->writer;
    }

    private function getExportPath(): string
    {
        if (null === $this->exportPath) {
            $this->exportPath = tempnam(sys_get_temp_dir(), 'xml_export_provider');
        }

        return $this->exportPath;
    }

    private function flush(\XMLWriter $writer): void
    {
        file_put_contents($this->getExportPath(), $writer->flush(true), FILE_APPEND);
    }

    private function serialize(\XMLWriter $writer, ?string $name, $data, ?int $key = null): void
    {
        if (is_scalar($data)) {
            $writer->startElement('property');
            if (null !== $name) {
                $writer->writeAttribute('name', $name);
            }
            if (null !== $key) {
                $writer->writeAttribute('key', $key);
            }
            if (is_string($data)) {
                $writer->writeCdata($data);
            } else {
                $writer->text($data);
            }
            $writer->endElement();
        } else {
            if (is_array($data)) {
                $writer->startElement('collection');
                if (null !== $name) {
                    $writer->writeAttribute('name', $name);
                }
                if (null !== $key) {
                    $writer->writeAttribute('key', $key);
                }
                $this->serializeCollection($writer, $data);
                $writer->endElement();
            } else {
                if ((string)$data) {
                    $writer->startElement('property');
                    if (null !== $name) {
                        $writer->writeAttribute('name', $name);
                    }
                    if (null !== $key) {
                        $writer->writeAttribute('key', $key);
                    }
                    $writer->writeCdata((string)$data);
                    $writer->endElement();
                }
            }
        }
    }

    private function serializeCollection(\XMLWriter $writer, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $this->serialize($writer, null, $value, $key);
            } else {
                $this->serialize($writer, $key, $value);
            }
        }
    }
}


