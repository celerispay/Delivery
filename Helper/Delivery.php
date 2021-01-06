<?php

namespace Boostsales\Delivering\Helper;

class Delivery extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_config;
    protected $_warehouseItemCollectionFactory;
    protected $_availabilityStatus;
    protected $_coreRegistry;
    protected $_storeManager;
    protected $_supplierProductCollectionFactory;

    public function __construct(
        \Magento\Framework\Registry $registry,
        \BoostMyShop\AvailabilityStatus\Model\Config $config,
        \BoostMyShop\AdvancedStock\Model\ResourceModel\Warehouse\Item\CollectionFactory $warehouseItemCollectionFactory,
        \BoostMyShop\AvailabilityStatus\Model\AvailabilityStatus $availabilityStatus,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \BoostMyShop\Supplier\Model\ResourceModel\Supplier\Product\CollectionFactory $supplierProductCollectionFactory
    ) {
        $this->_coreRegistry = $registry;
        $this->_config = $config;
        $this->_warehouseItemCollectionFactory = $warehouseItemCollectionFactory;
        $this->_availabilityStatus = $availabilityStatus;
        $this->_supplierProductCollectionFactory = $supplierProductCollectionFactory;
        $this->_storeManager = $storeManager;
    }

    public function getProductId()
    {
        $product = $this->_coreRegistry->registry('product');
        return $product ? $product->getId() : null;
    }
    private function getStore()
    {
        return $this->_storeManager->getStore();
    }

    private function getStocks($productId)
    {
        return $this->_warehouseItemCollectionFactory
            ->create()
            ->addProductFilter($productId)
            ->addInStockFilter()
            ->joinWarehouse()
            ->addVisibleOnFrontFilter();
    }

    public function getAvailableStock($productId)
    {
        $availabelStock = 0;

        if ($productId) {
            foreach ($this->getStocks($productId) as $stock) {
                $availabelStock = $stock->getwi_available_quantity();
            }
            return $availabelStock;
        }
    }

    private function getImglink($productStatus)
    {
        $currentStoreMediaUrl = $this->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $indicatorImage = $currentStoreMediaUrl . 'stockIndicator/stock-indicator' . $productStatus . '.png';
        return $indicatorImage;
    }

    public function getDeliveryHtml($productId)
    {
        $availableQty = $this->getAvailableStock($productId);
        $storeId = $this->getStore()->getId();
        $stockMessage = $this->_availabilityStatus->getAvailability($productId, $storeId);
        if ($availableQty > 0) {
            return $this->getInstockHtml($stockMessage);
        } elseif (!$this->_availabilityStatus->productIsAvailable($productId)) {
            return $this->getImageHtml($stockMessage, $this->getSupplierShippingDelay($storeId, $availableQty, $productId));
        } elseif ($availableQty == 0) {
            return $this->getImageHtml(null, $this->getSupplierShippingDelay($storeId, $availableQty, $productId));
        }
    }
    private function getInstockHtml($message)
    {
        $Img = $this->getImglink(null);
        $html = '<img title="inStock" src="' . $Img . '" style="margin-top: 5px;width:20px;height:20px;"/>' .$message["message"];
        return $html;
    }

    private function getImageHtml($message = null, $delay)
    {
        $html = '';
        if ($message) {
            $html .= $message["message"];
        }
        if ($delay >= 0 && $delay != null) {
            $html .= '<img title="' . $delay["message"] . '" src="' . $this->getImglink($delay['id']) . '" style="margin-top: 5px;width:20px;height:20px;"/>';
            $html .= '<span>' . $delay["message"] . '</span>';
        }
        return $html;
    }

    private function getLeadTimeRangeValue($days, $storeId)
    {
        for ($i = 0; $i < 10; $i++) {
            $from = $this->_config->getSetting('backorder/from_' . $i, $storeId);
            $to = $this->_config->getSetting('backorder/to_' . $i, $storeId);
            if (($from <= $days) && ($days <= $to)) {
                $backOrderMessage = $this->_config->getSetting('backorder/message_' . $i, $storeId);
                return array("id" => $i, "message" => $backOrderMessage);
            }
        }
    }

    private function getSupplierShippingDelay($storeId, $qty, $productId)
    {
        $delay = null;
        if ($qty > 0) {
            return $delay;
        }
        $collection = $this->_supplierProductCollectionFactory->create()->getSuppliers($productId);
        foreach ($collection as $item) {
            if ($item->getsp_shipping_delay()) {
                $shippingDelay = $item->getsp_shipping_delay();
            } else {
                $shippingDelay = $item->getsup_shipping_delay();
            }
            if ($shippingDelay) {
                $delay = $this->getLeadTimeRangeValue($shippingDelay, $storeId);
            }
            return $delay;
        }

    }
}
