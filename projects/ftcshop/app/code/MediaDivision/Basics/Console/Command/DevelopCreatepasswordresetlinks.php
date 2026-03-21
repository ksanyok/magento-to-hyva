<?php

// https://rematiptop.flyte228.lcube-server.de/de/customer/account/createPassword/?id=1&token=B1CrZZ9NReYDdYjFcPwvLi6p5anWURbS

namespace MediaDivision\Basics\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Customer\Model\AccountManagement;
use Magento\Framework\Math\Random;
use \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use \Magento\Customer\Api\CustomerRepositoryInterface;

class DevelopCreatepasswordresetlinks extends Command
{

    const DEBUG = "debug";

    private $debug = false;
    private $accountManagement;
    private $mathRandom;
    private $customerCollectionFactory;
    private $customerRepository;
    private $url = 'https://shop.ftc-cashmere.com/';
    private $urlPath = '/customer/account/createPassword/';
    private $language = [
        '0' => 'de-de',
        '1' => 'de-ch',
        '2' => 'en-ch',
        '3' => 'de-de',
        '4' => 'en-de',
        '5' => 'en-dk',
        '6' => 'en-nl',
        '7' => 'de-at',
        '8' => 'en-at',
        '9' => 'en-pl',
        '10' => 'en-se',
        '11' => 'en-eu',
        '12' => 'en-us',
        '13' => 'en-us',
    ];

    public function __construct(
            AccountManagement $accountManagement,
            Random $random,
            CustomerCollectionFactory $customerCollectionFactory,
            CustomerRepositoryInterface $customerRepositoryInterface
    ) {
        $this->accountManagement = $accountManagement;
        $this->mathRandom = $random;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->customerRepository = $customerRepositoryInterface;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug")
        ];
        $this->setName("develop:createpasswordresetlinks")
                ->setDescription("Erzeugt für die Kunden einen Passwort-ResetLink")->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        if ($input->getOption(self::DEBUG)) {
            $this->debug = true;
            echo "\nSetze debug mode.\n\n";
        }

        if ($this->debug) {
            echo "\n\nStarte develop:createpasswordresetlinks\n\n";
        }
        echo "Email;Sprache\n";
        $customerCollection = $this->customerCollectionFactory->create()->addAttributeToSelect('*')->load();
        foreach ($customerCollection as $customer) {
            echo $customer->getEmail() . ";";
            $language = $this->language[$customer->getStoreId()];
            echo $language . "\n";
        }
    }

    public function initiatePasswordReset($customer) {
        // No need to validate customer address while saving customer reset password token
        //$this->accountManagement->disableAddressValidation($customer);

        $newPasswordToken = $this->mathRandom->getUniqueHash();
        $apiCustomer = $this->customerRepository->getById($customer->getId());
        $this->accountManagement->changeResetPasswordLinkToken($apiCustomer, $newPasswordToken);
        return $newPasswordToken;
    }

}
