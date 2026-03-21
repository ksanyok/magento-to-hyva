<?php
namespace MediaDivision\SwatchImages\Controller\Cart;

use Magento\Framework\Controller\ResultFactory;

class Add extends \Magento\Framework\App\Action\Action {

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */

    protected $_pageFactory;
    protected $formKey;
    protected $cart;
    protected $_productRepository;


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        array $data = []
    ){
        $this->_pageFactory = $pageFactory;
        $this->formKey = $formKey;
        $this->_productRepository = $productRepository;
        $this->cart = $cart;
        return parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $post = $this->getRequest()->getPost('custom_products');
        $option = $this->getRequest()->getPost('options');
        $super_attribute = $this->getRequest()->getPost('super_attribute');
        try {
            $form_key = $this->formKey->getFormKey();

            foreach($post as $productId){
                //Load the product based on productID

                $_product = $this->_productRepository->getById($productId);
                if(!empty($option))
                {

                    $params = array(
                        'form_key' => $form_key,
                        'product' => $productId, //product Id
                        'qty'   =>1, //quantity of product
                        'options' => $option
                    );
                }
                else if(!empty($super_attribute[$productId]))
                {

                    $params = array(
                        'form_key' => $form_key,
                        'product' => $productId, //product Id
                        'qty'   =>1, //quantity of product
                        'super_attribute' => $super_attribute[$productId]
                    );
                }
                else {
                    $params = array(
                        'form_key' => $form_key,
                        'product' => $productId, //product Id
                        'qty'   =>1 //quantity of product
                    );
                }

                $this->cart->addProduct($_product, $params);

            }
            $this->cart->save();

            $this->messageManager->addSuccess(__('Add to cart successfully.'));

        }
        catch (\Exception $e) {
            $this->messageManager->addException($e, $e->getMessage());
        }
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }
}
?>
