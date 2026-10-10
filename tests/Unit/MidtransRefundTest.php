<?php

namespace Tests\Unit;

use App\Services\MidtransService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MidtransRefundTest
 *
 * Covers MidtransService::refundTransaction() — the Core API call
 * POST /v2/{order_id}/refund used by the admin refund flow.
 *
 * Normalized outcomes (mirrors cancelTransaction):
 *   refunded | duplicate | rejected | not_found | error
 */
class MidtransRefundTest extends TestCase
{
    private function service(): MidtransService
    {
        return new MidtransService();
    }

    public function test_refund_transaction_sends_correct_payload_and_returns_refunded_on_success(): void
    {
        Http::fake([
            '*/refund' => Http::response([
                'status_code'       => '200',
                'status_message'    => 'Success, refund request is approved',
                'transaction_status' => 'refund',
                'refund_amount'     => '50000.00',
            ], 200),
        ]);

        $result = $this->service()->refundTransaction(
            'ELCRAFT-REFUND-001',
            50000,
            'Barang rusak saat diterima',
            'RET-TEST-001'
        );

        $this->assertSame('refunded', $result['outcome']);
        $this->assertSame(200, $result['status_code']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v2/ELCRAFT-REFUND-001/refund')
                && $request->hasHeader('Authorization')
                && $request['refund_key'] === 'RET-TEST-001'
                && $request['amount'] == 50000
                && $request['reason'] === 'Barang rusak saat diterima';
        });
    }

    public function test_refund_transaction_returns_duplicate_for_406(): void
    {
        Http::fake([
            '*/refund' => Http::response([
                'status_code'    => '406',
                'status_message' => 'Duplicate refund ID',
            ], 406),
        ]);

        $result = $this->service()->refundTransaction('ELCRAFT-REFUND-002', 75000, 'Alasan', 'RET-DUP-001');

        $this->assertSame('duplicate', $result['outcome']);
        $this->assertSame(406, $result['status_code']);
    }

    public function test_refund_transaction_returns_rejected_for_414(): void
    {
        Http::fake([
            '*/refund' => Http::response([
                'status_code'    => '414',
                'status_message' => 'Refund request is rejected due to invalid amount',
            ], 414),
        ]);

        $result = $this->service()->refundTransaction('ELCRAFT-REFUND-003', 999999999, 'Alasan', 'RET-REJ-001');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame(414, $result['status_code']);
        $this->assertNotSame('', $result['message']);
    }

    public function test_refund_transaction_returns_not_found_for_404(): void
    {
        Http::fake([
            '*/refund' => Http::response([
                'status_code'    => '404',
                'status_message' => 'Order not found',
            ], 404),
        ]);

        $result = $this->service()->refundTransaction('ELCRAFT-REFUND-004', 10000, 'Alasan', 'RET-404-001');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function test_refund_transaction_returns_error_on_connection_failure(): void
    {
        Http::fake([
            '*/refund' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 15000 milliseconds');
            },
        ]);

        $result = $this->service()->refundTransaction('ELCRAFT-REFUND-005', 10000, 'Alasan', 'RET-ERR-001');

        $this->assertSame('error', $result['outcome']);
        $this->assertNull($result['status_code']);
        $this->assertNotSame('', $result['message']);
    }

    public function test_refund_transaction_returns_error_on_server_error_response(): void
    {
        Http::fake([
            '*/refund' => Http::response('Internal Server Error', 500),
        ]);

        $result = $this->service()->refundTransaction('ELCRAFT-REFUND-006', 10000, 'Alasan', 'RET-500-001');

        $this->assertSame('error', $result['outcome']);
        $this->assertSame(500, $result['status_code']);
    }
}
