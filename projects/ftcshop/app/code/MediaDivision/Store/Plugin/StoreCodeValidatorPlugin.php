<?php
declare(strict_types=1);

namespace MediaDivision\Store\Plugin;

use Laminas\Validator\Regex;
use Magento\Framework\Validator\AbstractValidator;
use Magento\Framework\Validator\RegexFactory;

use Magento\Store\Model\Validation\StoreCodeValidator;

class StoreCodeValidatorPlugin
{
    /**
     * @var RegexFactory
     */
    private $regexValidatorFactory;
    protected $_messages;

    /**
     * @param RegexFactory $regexValidatorFactory
     */
    public function __construct(RegexFactory $regexValidatorFactory)
    {
        $this->regexValidatorFactory = $regexValidatorFactory;
    }

    public function afterIsValid(StoreCodeValidator $subject, bool $result, $value): bool
    {
        // Get the validator and set the modified pattern.
        $validator = $this->regexValidatorFactory->create(['pattern' => '/^[a-z]+[a-z0-9_\-]*$/i']);
        $validator->setMessage(
            __(
                'The store code may contain only letters (a-z), numbers (0-9), underscores (_), or hyphens (-),'
                . ' and the first character must be a letter.'
            )
        );

        // Re-validate with the new pattern
        $result = $validator->isValid($value);
        $this->_messages = $validator->getMessages();

        return $result;
    }
}
