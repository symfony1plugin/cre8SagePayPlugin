<?php

class Cre8SagePayForm extends sfForm 
{
  
  /**
   * @var Cre8SagePay
   */
  private $cre8SagePay = null;
  
  public function __construct(Cre8SagePay $cre8SagePay, $defaults = array(), $options = array(), $CSRFSecret = null)
  {
    $this->cre8SagePay = $cre8SagePay;
    parent::__construct($defaults, $options, $CSRFSecret);
  }
  
  public function configure()
  {
    $this->setWidgets(array(
      'VPSProtocol' => new sfWidgetFormInputHidden(),
      'TxType' => new sfWidgetFormInputHidden(),
      'Vendor' => new sfWidgetFormInputHidden(),
      'Crypt' => new sfWidgetFormInputHidden()
    ));
    
    $this->setValidators(array(
      'VPSProtocol' => new sfValidatorNumber(),
      'TxType' => new sfValidatorString(),
      'Vendor' => new sfValidatorString(),
      'Crypt' => new sfValidatorString()
    ));
    
    $this->setDefaults(array(
      'VPSProtocol' => $this->cre8SagePay->getVPSProtocol(),
      'TxType' => $this->cre8SagePay->getTxType(),
      'Vendor' => $this->cre8SagePay->getVendorName(),
      'Crypt' => $this->cre8SagePay->getCrypt()
    ));
    
  }
}