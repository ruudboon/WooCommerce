<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC\Gateway;

use InvalidArgumentException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\MethodCollection;
use Mollie\WooCommerceTests\Stubs\varPolylangTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Gateway_Abstract as Testee;
use ReflectionMethod;
use UnexpectedValueException;
use WooCommerce;


use WP_Error;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Actions\expectDone;
use function Brain\Monkey\Functions\stubs;

/**
 * Class Mollie_WC_Helper_Settings_Test
 */
class Mollie_WC_Gateway_Abstract_Test extends TestCase
{
    /* -----------------------------------------------------------------
       getIconUrl Tests
       -------------------------------------------------------------- */


    /**
     * Test getIconUrl will return the url string even if Api returns empty
     *
     * @test
     */
    public function getIconUrlReturnsUrlStringFromAssets()
    {

        /*
        * Expect to call availablePaymentMethods() function and return false from the API
        */
        expect('esc_attr')
            ->once()
            ->withAnyArgs()
            ->andReturn("/images/ideal.svg");

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['getMollieMethodId']
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Expect testee is has id 'ideal'
        */
        $testee
            ->expects($this->once())
            ->method('getMollieMethodId')
            ->willReturn('ideal');

        /*
         * Execute test
         */
        $result = $testee->getIconUrl();
        $expected = '<img src="/images/ideal.svg" class="mollie-gateway-icon" />';

        self::assertStringEndsWith($expected, $result);

    }
    /* -----------------------------------------------------------------
     getReturnUrl Tests
     -------------------------------------------------------------- */

    /**
     * Test getReturnUrl
     * given polylang plugin is installed
     * then will return correct string
     */
    public function testgetReturnUrlReturnsStringwithPolylang()
    {
        /*
         * Setup Testee
         */
        $testee = $this->testPolylangTestee();

        //set variables
        $varStubs = new varPolylangTestsStubs();


        /*
        * Setup Stubs
        */
        $wcUrl = $this->createConfiguredMock(
            WooCommerce::class,
            ['api_request_url' => $varStubs->apiRequestUrl]
        );
        $wcOrder = $this->createConfiguredMock(
            \WC_Order::class,
            [
                'get_id' => $varStubs->orderId,
                'get_order_key' => $varStubs->orderKey,
            ]
        );


        /*
        * Expectations
        */
        expect('get_home_url')
            ->andReturn($varStubs->homeUrl);
        //get url from request
        expect('WC')
            ->andReturn($wcUrl);
        //delete url final slash
        expect('untrailingslashit')
            ->twice()
            ->andReturn($varStubs->untrailedUrl, $varStubs->untrailedWithParams);
        expect('idn_to_ascii')
            ->andReturn($varStubs->untrailedUrl);
        //get order id and key and append to the the url

        $testee
            ->expects($this->once())
            ->method('appendOrderArgumentsToUrl')
            ->with($varStubs->orderId, $varStubs->orderKey, $varStubs->untrailedUrl)
            ->willReturn($varStubs->urlWithParams);

        //check for multilanguage plugin enabled and receive url
        $testee
            ->expects($this->once())
            ->method('getSiteUrlWithLanguage')
            ->willReturn("{$varStubs->afterLangUrl}");

        expect('mollieWooCommerceDebug')
            ->withAnyArgs();

        /*
         * Execute test
         */
        $result = $testee->getReturnUrl($wcOrder);

        self::assertEquals($varStubs->result, $result);
    }

    /* -----------------------------------------------------------------
      getWebhookUrl Tests
      -------------------------------------------------------------- */
    /**
     * Test getWebhookUrl
     * given polylang plugin is installed
     * then will return correct string
     */
    public function testgetWebhookUrlReturnsStringwithPolylang()
    {
        /*
         * Setup Testee
         */
        $testee = $this->testPolylangTestee();

        //set variables
        $varStubs = new varPolylangTestsStubs();

        /*
        * Setup Stubs
        */
        $wcUrl = $this->createConfiguredMock(
            WooCommerce::class,
            ['api_request_url' => $varStubs->apiRequestUrl]
        );
        $wcOrder = $this->createConfiguredMock(
            \WC_Order::class,
            [
                'get_id' => $varStubs->orderId,
                'get_order_key' => $varStubs->orderKey,
            ]
        );


        /*
        * Expectations
        */
        expect('get_home_url')
            ->andReturn($varStubs->homeUrl);
        //get url from request
        expect('WC')
            ->andReturn($wcUrl);
        //delete url final slash
        expect('untrailingslashit')
            ->twice()
            ->andReturn($varStubs->untrailedUrl, $varStubs->untrailedWithParams);
        expect('idn_to_ascii')
            ->andReturn($varStubs->untrailedUrl);
        //get order id and key and append to the the url
        $testee
            ->expects($this->once())
            ->method('appendOrderArgumentsToUrl')
            ->with($varStubs->orderId, $varStubs->orderKey, $varStubs->untrailedUrl)
            ->willReturn($varStubs->urlWithParams);
        //check for multilanguage plugin enabled, receives url and adds it
        $testee
            ->expects($this->once())
            ->method('getSiteUrlWithLanguage')
            ->willReturn("{$varStubs->homeUrl}");
        expect('mollieWooCommerceDebug')
            ->withAnyArgs();

        /*
         * Execute test
         */
        $result = $testee->getWebhookUrl($wcOrder);

        self::assertEquals(
            "{$varStubs->homeUrl}/wc-api/mollie_return?order_id={$varStubs->orderId}&key=wc_order_{$varStubs->orderKey}",
            $result
        );
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function testPolylangTestee()
    {
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                ['getSiteUrlWithLanguage', 'appendOrderArgumentsToUrl']
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        return $testee;
    }

    /**
     * @return array
     */
    public function testPolylangVariables()
    {
        $orderId = $this->faker->randomDigit;
        $orderKey = $this->faker->word;
        $homeUrl = rtrim($this->faker->url, '/\\');
        $apiRequestUrl = "{$homeUrl}/wc-api/mollie_return";
        $untrailedUrl = rtrim($apiRequestUrl, '/\\');
        $urlWithParams
            = "{$untrailedUrl}/?order_id={$orderId}&key=wc_order_{$orderKey}";
        $untrailedWithParams = rtrim($urlWithParams, '/\\');
        return array(
            $orderId,
            $orderKey,
            $homeUrl,
            $apiRequestUrl,
            $untrailedUrl,
            $urlWithParams,
            $untrailedWithParams
        );
    }

    /* -----------------------------------------------------------------
      getReturnRedirectUrlForOrder Tests
      -------------------------------------------------------------- */
    /**
     * Test getReturnRedirectUrlForOrder
     * given order does NOT need payment
     * then will fire
     * do_action('mollie-payments-for-woocommerce_customer_return_payment_success', $order)
     *
     * @throws \Brain\Monkey\Expectation\Exception\ExpectationArgsRequired
     */
    public function testgetReturnRedirectUrlForOrderHookIsSuccessWhenNeedPaymentFalse()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['orderNeedsPayment', 'get_return_url']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Setup Stubs
        */
        $order = $this->createMock(\WC_Order::class);

        /*
        * Expectations
        */
        expect('mollieWooCommerceDebug')
            ->once();
        $testee
            ->expects($this->once())
            ->method('orderNeedsPayment')
            ->with($order)
            ->willReturn(false);
        expectDone('mollie-payments-for-woocommerce_customer_return_payment_success')
            ->once()
            ->with($order);
        $testee
            ->expects($this->once())
            ->method('get_return_url')
            ->with($order);
        /*
         * Execute test
         */
        $testee->getReturnRedirectUrlForOrder($order);
    }

    /**
     * Test getReturnRedirectUrlForOrder
     * given order does need payment
     * When there is exception thrown
     * then will fire
     * do_action('mollie-payments-for-woocommerce_customer_return_payment_failed', $order)
     *
     * @throws \Brain\Monkey\Expectation\Exception\ExpectationArgsRequired
     */
    public function testgetReturnRedirectUrlForOrderHookIsFailedWhenNeedPaymentException()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['orderNeedsPayment', 'get_return_url', 'paymentObject', 'activePaymentObject']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Setup Stubs
        */
        $order = $this->createMock(\WC_Order::class);
        $payment = $this->createMock(\Mollie_WC_Payment_Object::class);

        /*
        * Expectations
        */
        expect('mollieWooCommerceDebug')
            ->twice();
        $testee
            ->expects($this->once())
            ->method('orderNeedsPayment')
            ->with($order)
            ->willReturn(true);
        $testee
            ->expects($this->once())
            ->method('paymentObject')
            ->willReturn($payment);
        $testee
            ->expects($this->once())
            ->method('activePaymentObject')
            ->willThrowException(new UnexpectedValueException());
        expect('mollieWooCommerceNotice')
            ->once();
        expectDone('mollie-payments-for-woocommerce_customer_return_payment_failed')
            ->once()
            ->with($order);
        $testee
            ->expects($this->once())
            ->method('get_return_url')
            ->with($order);
        /*
         * Execute test
         */
        $testee->getReturnRedirectUrlForOrder($order);
    }

    public function testValidFilters()
    {
        $currency = 'USD';
        $order_total = 42.0;
        $billing_country = 'NL';
        $payment_locale = '';

        list($sut, $sutReflection) = $this->createSutForFilters();

        $sut
            ->expects($this->once())
            ->method('getAmountValue')
            ->willReturn('42.00');

        $filters = $sutReflection->invoke(
            $sut,
            $currency,
            $order_total,
            $payment_locale,
            $billing_country
        );

        $expected = [
            'amount' => [
                'currency' => $currency,
                'value' => '42.00',
            ],
            'locale' => '',
            'billingCountry' => 'NL',
            'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_ONEOFF,
            'resource' => 'orders',
        ];

        self::assertEquals($expected, $filters);
    }

    public function testInvalidFilterAmount() {

        $this->expectException(InvalidArgumentException::class);

        $currency = 'USD';
        $order_total = 0.0;
        $payment_locale = '';
        $billing_country = '';

        list($sut, $sutReflection) = $this->createSutForFilters();

        $sutReflection->invoke(
            $sut,
            $currency,
            $order_total,
            $payment_locale,
            $billing_country
        );
    }

    public function testInvalidFilterCurrency()
    {
        $this->expectException(InvalidArgumentException::class);

        $currency = 'SURINAAMSE DOLLAR';
        $order_total = 42.0;
        $payment_locale = '';
        $billing_country = '';

        list($sut, $sutReflection) = $this->createSutForFilters();

        $sutReflection->invoke(
            $sut,
            $currency,
            $order_total,
            $payment_locale,
            $billing_country
        );
    }

    public function testInvalidFilterBillingCountry()
    {
        $this->expectException(InvalidArgumentException::class);

        $currency = 'USD';
        $order_total = 42.0;
        $payment_locale = '';
        $billing_country = 'Nederland';

        list($sut, $sutReflection) = $this->createSutForFilters();

        $sut
            ->expects($this->once())
            ->method('getAmountValue')
            ->willReturn('42.00');

        $sutReflection->invoke(
            $sut,
            $currency,
            $order_total,
            $payment_locale,
            $billing_country
        );
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    private function createSutForFilters()
    {
        $sut = $this
            ->getMockBuilder(Testee::class)
            ->setMethods([
                'init_settings',
                'get_option',
                'process_admin_options',
                'formatCurrencyValue',
                'getAmountValue',
            ])
            ->getMockForAbstractClass();

        $sutReflection = new ReflectionMethod($sut, 'getFilters');
        $sutReflection->setAccessible(true);

        return array($sut, $sutReflection);
    }

    /* -----------------------------------------------------------------
      is_available Tests
      -------------------------------------------------------------- */

    /**
     * Scenario: when not enabled, gateway is not available to show
     * @test
     */
    public function isNotAvailable_WhenNotEnabledGateway()
    {
        $expected = false;

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            []
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        $testee->enabled = 'no';

        $result = $testee->is_available();

        self::assertEquals($expected, $result);

    }
    /**
     * Scenario: when in cart but quantity zero, gateway is available to show
     * @test
     */
    public function isAvailable_WhenQuantityZero()
    {
        $expected = true;
        stubs(
            [
                'WC' => $this->wooCommerce(),
            ]
        );

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['get_order_total']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        $testee->enabled = 'yes';
        $testee
            ->expects($this->once())
            ->method('get_order_total')
            ->willReturn(0);

        $result = $testee->is_available();

        self::assertEquals($expected, $result);
    }
    /**
     * Scenario: gateway is available if allowed from the api and allowed_countries option is empty
     * @test
     */
    public function isAvailable_WhenEmptyAllowedCountries()
    {

        $expected = true;
        stubs(
            [
                'WC' => $this->wooCommerce(),
                'get_woocommerce_currency'=>'EUR',
                'get_option'=>'IT'
            ]
        );

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['get_order_total', 'getAmountValue', 'isAvailableMethodInCheckout','get_option']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        $testee->enabled = 'yes';
        $testee
            ->expects($this->exactly(2))
            ->method('get_order_total')
            ->willReturn(1);
        $testee
            ->expects($this->once())
            ->method('getAmountValue')
            ->willReturn(1);
        $testee
            ->expects($this->once())
            ->method('isAvailableMethodInCheckout')
            ->willReturn(true);
        $testee
            ->expects($this->once())
            ->method('get_option')
            ->willReturn([]);

        $result = $testee->is_available();

        self::assertEquals($expected, $result);
    }
    /**
     * Scenario: gateway is NOT available if allowed from the api
     * but billing country is not in the Allowed countries option
     * @test
     */
    public function isNOTAvailable_WhenNotInAllowedCountries()
    {

        $expected = false;
        stubs(
            [
                'WC' => $this->wooCommerce(),
                'get_woocommerce_currency'=>'EUR',
                'get_option'=>'IT'
            ]
        );

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['get_order_total', 'getAmountValue', 'isAvailableMethodInCheckout','get_option']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        $testee->enabled = 'yes';
        $testee
            ->expects($this->exactly(2))
            ->method('get_order_total')
            ->willReturn(1);
        $testee
            ->expects($this->once())
            ->method('getAmountValue')
            ->willReturn(1);
        $testee
            ->expects($this->once())
            ->method('isAvailableMethodInCheckout')
            ->willReturn(true);
        $testee
            ->expects($this->once())
            ->method('get_option')
            ->willReturn(['ES']);

        $result = $testee->is_available();

        self::assertEquals($expected, $result);
    }
    /**
     * Scenario: gateway is available if allowed from the api
     * AND billing country is in the Allowed countries option
     * @test
     */
    public function isAvailable_WhenInAllowedCountries()
    {

        $expected = true;
        stubs(
            [
                'WC' => $this->wooCommerce(),
                'get_woocommerce_currency'=>'EUR',
                'get_option'=>'IT'
            ]
        );

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['get_order_total', 'getAmountValue', 'isAvailableMethodInCheckout','get_option']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        $testee->enabled = 'yes';
        $testee
            ->expects($this->exactly(2))
            ->method('get_order_total')
            ->willReturn(1);
        $testee
            ->expects($this->once())
            ->method('getAmountValue')
            ->willReturn(1);
        $testee
            ->expects($this->once())
            ->method('isAvailableMethodInCheckout')
            ->willReturn(true);
        $testee
            ->expects($this->once())
            ->method('get_option')
            ->willReturn(['IT']);

        $result = $testee->is_available();

        self::assertEquals($expected, $result);
    }
    /**
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wooCommerce() {
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [

            ]
        );
        $item->cart = true;
        $item->customer = $this->wcCustomer();

        return $item;
    }
    /**
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCustomer()
    {
        $item = $this->createConfiguredMock(
            'WC_Customer',
            [
                'get_billing_country' => 'IT'

            ]
        );

        return $item;
    }
}
