<?php

namespace Webgriffe\LibUnicreditImprese;

use Psr\Log\LoggerInterface;

use Webgriffe\LibUnicreditImprese\PaymentInit\Request as InitRequest;
use Webgriffe\LibUnicreditImprese\PaymentInit\Response as InitResponse;

use Webgriffe\LibUnicreditImprese\PaymentVerify\Request as VerifyRequest;
use Webgriffe\LibUnicreditImprese\PaymentVerify\Response as VerifyResponse;
use Webgriffe\LibUnicreditImprese\SoapClient\WrapperInterface;

/**
 * Class Client
 * @package Webgriffe\LibUnicreditImprese
 */
class Client
{
    const TRANSACTION_TYPE_AUTH = 'AUTH';
    const TRANSACTION_TYPE_PURCHASE = 'PURCHASE';

    const CURRENCY_CODE_EUR = 'EUR';
    const CURRENCY_CODE_USD = 'USD';
    const LANGUAGE_ITA = 'IT';
    const LANGUAGE_ENG = 'EN';

    const TRANSACTION_IN_PROGRESS_RETURN_CODE = 'IGFS_814';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $kSig;

    /**
     * @var string
     */
    protected $tId;

    /**
     * @var WrapperInterface
     */
    protected $soapClientWrapper;

    /**
     * Client constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->soapClientWrapper = new SoapClient\Wrapper();
    }

    /**
     * @param WrapperInterface $wrapper
     */
    public function setSoapClientWrapper(WrapperInterface $wrapper)
    {
        $this->soapClientWrapper = $wrapper;
    }

    /**
     * @return mixed
     */
    public function getKSig()
    {
        return $this->kSig;
    }

    /**
     * @param mixed $kSig
     */
    public function setKSig($kSig)
    {
        $this->kSig = $kSig;
    }

    /**
     * @return mixed
     */
    public function getTid()
    {
        return $this->tId;
    }
    
    /**
     * @param mixed $tId
     */
    public function setTid($tId)
    {
        $this->tId = $tId;
    }

    /**
     * @param $isTestMode boolean
     * @param $kSig string Secret signature key
     * @param $tid string Terminal ID
     * @param $wsdl string WSDL URL
     */
    public function init($isTestMode, $kSig, $tid, $wsdl)
    {
        if (!$kSig) {
            throw new \InvalidArgumentException('Missing signature key');
        }

        if (!$tid) {
            throw new \InvalidArgumentException('Missing terminal id');
        }

        if (!$wsdl) {
            throw new \InvalidArgumentException('Missing WSDL URL');
        }

        $this->kSig = $kSig;
        $this->tId = $tid;
        $soapOptions = array(
            'compression' => SOAP_COMPRESSION_ACCEPT,
            'soap_version' => SOAP_1_1,
        );
        if (!extension_loaded('soap')) {
            if ($this->logger) {
                $this->logger->critical(
                    'Unable to create the webserver client.'.PHP_EOL.
                    'The PHP_SOAP extension is required to use this library.'
                );
            }
            throw new \RuntimeException('PHP SOAP extension is required.');
        }

        if ($isTestMode) {
            //Allow self-signed certificates in test mode
            $contextOptions = array(
                'ssl' => array(
                    'allow_self_signed' => true,
                )
            );
            $sslContext = stream_context_create($contextOptions);
            $soapOptions['stream_context'] = $sslContext;
        }

        $this->soapClientWrapper->initialize($wsdl, $soapOptions);
    }

    /**
     * @see https://pagamenti.unicredit.it/UNI_CG_SERVICES/services/PaymentInitGatewayPort?xsd=dto/init/PaymentInit.xsd
     *
     * @param $trType string One of AUTH, PURCHASE, VERIFY, TOKENIZE, DELETE or MODIFY
     * @param $floatAmount float Payment amount. Must be a value with no more than 2 decimal places
     * @param $langId
     * @param $notifyUrl
     * @param $errorUrl
     * @param $currencyCode string Only EUR and USD are allowed
     * @param $shopId
     * @param $shopUserRef
     * @param $shopUserName
     * @param $description
     * @param $shopUserAccount
     * @param $addInfo1
     * @param $addInfo2
     * @param $addInfo3
     * @param $addInfo4
     * @param $addInfo5
     * @param $recurrent
     * @param $freeText
     * @param $paymentReason
     * @param $validityExpire
     *
     * @return InitResponse
     * @throws \Exception
     */
    public function paymentInit(
        $trType,
        $floatAmount,
        $langId,
        $notifyUrl,
        $errorUrl,
        $currencyCode = self::CURRENCY_CODE_EUR,
        $shopId = null,
        $shopUserRef = null,
        $shopUserName = null,
        $description = null,
        $shopUserAccount = null,
        $addInfo1 = null,
        $addInfo2 = null,
        $addInfo3 = null,
        $addInfo4 = null,
        $addInfo5 = null,
        $recurrent = 0,
        $freeText = null,
        $paymentReason = null,
        $validityExpire = null
    ) {
        if (!$this->isInitialized()) {
            throw new \LogicException('Please initialize the client before trying to perform paymentInit operations');
        }

        if (empty($trType) || empty($floatAmount) || empty($langId) || empty($notifyUrl) || empty($errorUrl)) {
            throw new \InvalidArgumentException("Cannot invoke webservice, some mandatory field is missing");
        }
        
        $currencyCode = strtoupper($currencyCode);
        if ($currencyCode != self::CURRENCY_CODE_EUR && $currencyCode != self::CURRENCY_CODE_USD) {
            throw new \InvalidArgumentException(sprintf('Unsupported currency specified %s.', $currencyCode));
        }

        //@todo more validation

        try {
            $request = new InitRequest();

            $request->setShopId($shopId);
            $request->setShopUserRef($shopUserRef);
            $request->setShopUserName($shopUserName);
            $request->setShopUserAccount($shopUserAccount);
            $request->setTrType($trType);
            $request->setAmount($floatAmount);
            $request->setCurrencyCode($currencyCode);
            $request->setLangId($langId);
            $request->setNotifyUrl($notifyUrl);
            $request->setErrorUrl($errorUrl);
            $request->setAddInfo1($addInfo1);
            $request->setAddInfo2($addInfo2);
            $request->setAddInfo3($addInfo3);
            $request->setAddInfo4($addInfo4);
            $request->setAddInfo5($addInfo5);
            $request->setDescription($description);
            $request->setRecurrent($recurrent);
            $request->setPaymentReason($paymentReason);
            $request->setFreeText($freeText);
            $request->setValidityExpire($validityExpire);
            $request->setTid($this->tId);
        } catch (\Exception $ex) {
            $this->logger->critical($ex->getMessage());
            throw $ex;
        }

        $signatureCalculator = new SignatureCalculator();
        $signatureCalculator->sign($request, $this->kSig);

        return new InitResponse(
            $this->soapClientWrapper->init(
                array('request' => ($request->toArray()))
            ),
            $this->logger
        );
    }

    /**
     * @param $shopId
     * @param $paymentId
     *
     * @return VerifyResponse
     * @throws \Exception
     */
    public function paymentVerify($shopId, $paymentId)
    {
        if (!$this->isInitialized()) {
            throw new \LogicException('Please initialize the client before trying to perform verify operations');
        }

        if (empty($shopId) || empty($paymentId)) {
            throw new \InvalidArgumentException("Cannot invoke webservice, some mandatory field is missing");
        }

        $request = new VerifyRequest();
        $request->setTid($this->tId);
        $request->setShopId($shopId);
        $request->setPaymentId($paymentId);
        $signatureCalculator = new SignatureCalculator();
        $signatureCalculator->sign($request, $this->kSig);
        $request = array('request' => ($request->toArray()));
        $this->logger->debug(print_r($request, true));

        return new VerifyResponse(
            $this->soapClientWrapper->verify($request),
            $this->logger
        );
    }

    /**
     * @return bool
     */
    protected function isInitialized()
    {
        return isset($this->tId) && isset($this->kSig) && $this->soapClientWrapper->isInitialized();
    }

    /**
     * @deprecated Use isInitialized instead
     *
     * @return bool
     */
    protected function canExecute()
    {
        return $this->isInitialized();
    }
}
