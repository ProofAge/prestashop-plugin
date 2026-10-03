<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;

class ProofageWebhookModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function postProcess()
    {
        if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') !== 'POST') {
            $this->respond(405, 'Method not allowed');
        }
        $rawBody = (string) file_get_contents('php://input');
        $factory = ServiceFactory::forShop((int) $this->context->shop->id);

        try {
            $result = $factory->webhookHandler()->handle(self::requestHeaders(), $rawBody);
        } catch (Throwable $e) {
            $factory->logger()->warning('Webhook processing failed: ' . $e->getMessage());
            $this->respond(500, 'Processing failed');

            return;
        }

        $this->respond($result->status, $result->message);
    }

    // ProofAge must reach this endpoint even when the shop is closed or restricted.
    protected function displayMaintenancePage()
    {
    }

    protected function displayRestrictedCountryPage()
    {
    }

    protected function sslRedirection()
    {
    }

    /**
     * @return array<string,string>
     */
    private static function requestHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headers[str_replace('_', '-', substr($name, 5))] = (string) $value;
            }
        }

        return $headers;
    }

    private function respond(int $status, string $message)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['message' => $message]);
        exit;
    }
}
