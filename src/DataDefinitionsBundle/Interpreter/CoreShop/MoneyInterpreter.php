<?php


namespace Wvision\Bundle\DataDefinitionsBundle\Interpreter\CoreShop;


use CoreShop\Component\Core\Repository\CurrencyRepositoryInterface;
use CoreShop\Component\Currency\Model\CurrencyInterface;
use CoreShop\Component\Currency\Model\Money;
use Pimcore\Model\DataObject\Concrete;
use Wvision\Bundle\DataDefinitionsBundle\Interpreter\InterpreterInterface;
use Wvision\Bundle\DataDefinitionsBundle\Model\DataDefinitionInterface;
use Wvision\Bundle\DataDefinitionsBundle\Model\MappingInterface;

final class MoneyInterpreter implements InterpreterInterface
{
    private $currencyRepository;

    public function __construct(CurrencyRepositoryInterface $currencyRepository)
    {
        $this->currencyRepository = $currencyRepository;
    }

    public function interpret(
        Concrete $object,
        $value,
        MappingInterface $map,
        $data,
        DataDefinitionInterface $definition,
        $params,
        $configuration
    )
    {
        $value = $this->getValue($value, $configuration);
        $currency = $this->resolveCurrency($value, $configuration);

        if (null === $currency) {
            return null;
        }

        return new Money($value, $currency);
    }

    /**
     * @param $value
     * @param $configuration
     *
     * @return int
     */
    private function getValue($value, $configuration)
    {
        $inputIsFloat = $configuration['isFloat'];

        $value = preg_replace("/[^0-9,.]+/", "", $value);

        if (\is_string($value)) {
            $value = str_replace(',', '.', $value);
            $value = (float)$value;
        }

        if ($inputIsFloat) {
            $value = (int)round(round($value, 2) * 100, 0);
        }

        return (int)$value;
    }

    /**
     * @param string $value
     * @param array $configuration
     *
     * @return CurrencyInterface|null
     */
    private function resolveCurrency($value, $configuration)
    {
        $currency = null;

        if (preg_match('/^\pL+$/u', $value)) {
            $currencyCode = preg_replace("/[^a-zA-Z]+/", "", $value);

            $currency = $this->currencyRepository->getByCode($currencyCode);
        }

        if ($currency === null && isset($configuration['currency']) && null !== $configuration['currency']) {
            $currency = $this->currencyRepository->find($configuration['currency']);
        }

        return $currency;
    }
}
