<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Api\ApiClient;
use ProofAge\PrestaShop\Api\CurlTransport;
use ProofAge\PrestaShop\Api\RequestSigner;
use ProofAge\PrestaShop\Api\WebhookSignatureVerifier;
use ProofAge\PrestaShop\Rules\RuleEngine;
use ProofAge\PrestaShop\Support\Clock;
use ProofAge\PrestaShop\Support\Logger;
use ProofAge\PrestaShop\Support\ModuleInfo;
use ProofAge\PrestaShop\Support\SystemClock;
use ProofAge\PrestaShop\Verification\VerificationService;
use ProofAge\PrestaShop\Verification\VisitorState;
use ProofAge\PrestaShop\Verification\WebhookHandler;

/**
 * Builds the module's services for one shop; memoised for the duration of a request.
 */
final class ServiceFactory
{
    /** @var array<int,ServiceFactory> */
    private static $instances = [];

    /** @var int */
    private $idShop;
    /** @var array<string,object> */
    private $services = [];

    private function __construct(int $idShop)
    {
        $this->idShop = $idShop;
    }

    public static function forShop(int $idShop): self
    {
        if (!isset(self::$instances[$idShop])) {
            self::$instances[$idShop] = new self($idShop);
        }

        return self::$instances[$idShop];
    }

    public static function reset(): void
    {
        self::$instances = [];
    }

    public function idShop(): int
    {
        return $this->idShop;
    }

    public function settings(): Settings
    {
        return $this->once('settings', function () {
            return new Settings($this->idShop);
        });
    }

    public function clock(): Clock
    {
        return $this->once('clock', function () {
            return new SystemClock();
        });
    }

    public function logger(): Logger
    {
        return $this->once('logger', function () {
            return new PrestaShopLogger();
        });
    }

    public function api(): ApiClient
    {
        return $this->once('api', function () {
            $settings = $this->settings();

            return new ApiClient(
                new CurlTransport(10, 'ProofAge-PrestaShop/' . ModuleInfo::VERSION . ' PrestaShop/' . _PS_VERSION_),
                $settings->apiUrl(),
                $settings->publicKey(),
                new RequestSigner($settings->secretKey())
            );
        });
    }

    public function verifications(): DbVerificationRepository
    {
        return $this->once('verifications', function () {
            return new DbVerificationRepository();
        });
    }

    public function customers(): DbCustomerVerificationRepository
    {
        return $this->once('customers', function () {
            return new DbCustomerVerificationRepository();
        });
    }

    public function deliveries(): DbWebhookDeliveryRepository
    {
        return $this->once('deliveries', function () {
            return new DbWebhookDeliveryRepository();
        });
    }

    public function orders(): DbOrderSnapshotRepository
    {
        return $this->once('orders', function () {
            return new DbOrderSnapshotRepository();
        });
    }

    public function ruleEngine(): RuleEngine
    {
        return $this->once('ruleEngine', function () {
            return new RuleEngine($this->settings()->ruleSet(), new DbCategoryLookup());
        });
    }

    public function verificationService(): VerificationService
    {
        return $this->once('verificationService', function () {
            return new VerificationService(
                $this->api(),
                $this->verifications(),
                $this->customers(),
                $this->orders(),
                $this->clock(),
                $this->settings()->ttlPolicy(),
                $this->logger()
            );
        });
    }

    public function visitorState(): VisitorState
    {
        return $this->once('visitorState', function () {
            return new VisitorState($this->verifications(), $this->customers(), $this->clock());
        });
    }

    public function webhookHandler(): WebhookHandler
    {
        return $this->once('webhookHandler', function () {
            $secret = $this->settings()->secretKey();

            return new WebhookHandler(
                $secret === '' ? null : new WebhookSignatureVerifier($secret),
                $this->deliveries(),
                $this->verifications(),
                $this->verificationService(),
                $this->clock(),
                $this->logger()
            );
        });
    }

    /**
     * @return mixed
     */
    private function once(string $name, callable $build)
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $build();
        }

        return $this->services[$name];
    }
}
