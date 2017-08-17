<?php

namespace craft\commerce\omnipay\base;

use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\records\Transaction as TransactionRecord;
use Omnipay\Common\CreditCard;

/**
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     1.0
 */
abstract class CreditCardGateway extends Gateway
{
    use CreditCardGatewayTrait;

    /**
     * @inheritdoc
     */
    protected function createRequest(Transaction $transaction, BasePaymentForm $form = null)
    {
        // For authorize and capture we're referring to a transaction that already took place so no card or item shenanigans.
        if (in_array($transaction->type, [TransactionRecord::TYPE_REFUND, TransactionRecord::TYPE_CAPTURE], false)) {
            $request = $this->createPaymentRequest($transaction);
        } else {
            $order = $transaction->getOrder();

            $card = $this->createCard($order, $form);
            $itemBag = $this->getItemBagForOrder($order);

            $request = $this->createPaymentRequest($transaction, $card, $itemBag);
            $this->populateRequest($request, $form);
        }

        return $request;
    }

    /**
     * @inheritdoc
     */
    public function createCard(Order $order, BasePaymentForm $paymentForm): CreditCard {

        $card = new CreditCard;

        /** @var CreditCardPaymentForm $paymentForm */
        $this->populateCard($card, $paymentForm);

        if ($order->billingAddressId) {
            $billingAddress = $order->billingAddress;
            if ($billingAddress) {
                // Set top level names to the billing names
                $card->setFirstName($billingAddress->firstName);
                $card->setLastName($billingAddress->lastName);

                $card->setBillingFirstName($billingAddress->firstName);
                $card->setBillingLastName($billingAddress->lastName);
                $card->setBillingAddress1($billingAddress->address1);
                $card->setBillingAddress2($billingAddress->address2);
                $card->setBillingCity($billingAddress->city);
                $card->setBillingPostcode($billingAddress->zipCode);
                if ($billingAddress->getCountry()) {
                    $card->setBillingCountry($billingAddress->getCountry()->iso);
                }
                if ($billingAddress->getState()) {
                    $state = $billingAddress->getState()->abbreviation ?: $billingAddress->getState()->name;
                    $card->setBillingState($state);
                } else {
                    $card->setBillingState($billingAddress->getStateText());
                }
                $card->setBillingPhone($billingAddress->phone);
                $card->setBillingCompany($billingAddress->businessName);
                $card->setCompany($billingAddress->businessName);
            }
        }

        if ($order->shippingAddressId) {
            $shippingAddress = $order->shippingAddress;
            if ($shippingAddress) {
                $card->setShippingFirstName($shippingAddress->firstName);
                $card->setShippingLastName($shippingAddress->lastName);
                $card->setShippingAddress1($shippingAddress->address1);
                $card->setShippingAddress2($shippingAddress->address2);
                $card->setShippingCity($shippingAddress->city);
                $card->setShippingPostcode($shippingAddress->zipCode);

                if ($shippingAddress->getCountry()) {
                    $card->setShippingCountry($shippingAddress->getCountry()->iso);
                }

                if ($shippingAddress->getState()) {
                    $state = $shippingAddress->getState()->abbreviation ?: $shippingAddress->getState()->name;
                    $card->setShippingState($state);
                } else {
                    $card->setShippingState($shippingAddress->getStateText());
                }

                $card->setShippingPhone($shippingAddress->phone);
                $card->setShippingCompany($shippingAddress->businessName);
            }
        }

        $card->setEmail($order->email);

        return $card;
    }

    /**
     * @inheritdoc
     */
    public function populateCard($card, CreditCardPaymentForm $paymentForm)
    {
        if (!$card instanceof CreditCard) {
            return;
        }

        $card->setFirstName($paymentForm->firstName);
        $card->setLastName($paymentForm->lastName);
        $card->setNumber($paymentForm->number);
        $card->setExpiryMonth($paymentForm->month);
        $card->setExpiryYear($paymentForm->year);
        $card->setCvv($paymentForm->cvv);
    }
}