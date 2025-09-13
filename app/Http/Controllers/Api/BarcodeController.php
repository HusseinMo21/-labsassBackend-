<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BarcodeController extends Controller
{
    protected $barcodeService;

    public function __construct(BarcodeService $barcodeService)
    {
        $this->barcodeService = $barcodeService;
    }

    /**
     * Scan a barcode and return comprehensive data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function scan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid barcode format',
                'errors' => $validator->errors(),
            ], 422);
        }

        $barcode = $request->input('barcode');
        
        Log::info('Barcode scan request', [
            'barcode' => $barcode,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        $data = $this->barcodeService->getBarcodeData($barcode);

        if (!$data['success']) {
            return response()->json($data, 404);
        }

        return response()->json($data);
    }

    /**
     * Parse a barcode without looking up data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function parse(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid barcode format',
                'errors' => $validator->errors(),
            ], 422);
        }

        $barcode = $request->input('barcode');

        try {
            $parsed = $this->barcodeService->parseBarcode($barcode);
            
            return response()->json([
                'success' => true,
                'barcode' => $barcode,
                'parsed' => $parsed,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'barcode' => $barcode,
            ], 422);
        }
    }

    /**
     * Validate a barcode format.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'error' => 'Invalid input',
                'errors' => $validator->errors(),
            ], 422);
        }

        $barcode = $request->input('barcode');
        $isValid = $this->barcodeService->isValidBarcode($barcode);

        return response()->json([
            'valid' => $isValid,
            'barcode' => $barcode,
        ]);
    }

    /**
     * Get sample by barcode.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSample(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid barcode format',
                'errors' => $validator->errors(),
            ], 422);
        }

        $barcode = $request->input('barcode');
        $sample = $this->barcodeService->findSampleByBarcode($barcode);

        if (!$sample) {
            return response()->json([
                'success' => false,
                'error' => 'Sample not found',
                'barcode' => $barcode,
            ], 404);
        }

        $sample->load(['labRequest.patient', 'labRequest.visit']);

        return response()->json([
            'success' => true,
            'sample' => $sample,
            'lab_request' => $sample->labRequest,
            'patient' => $sample->labRequest?->patient,
            'visit' => $sample->labRequest?->visit,
        ]);
    }

    /**
     * Get lab request by lab number.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLabRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lab_no' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid lab number format',
                'errors' => $validator->errors(),
            ], 422);
        }

        $labNo = $request->input('lab_no');
        $labRequest = $this->barcodeService->findLabRequestByLabNo($labNo);

        if (!$labRequest) {
            return response()->json([
                'success' => false,
                'error' => 'Lab request not found',
                'lab_no' => $labNo,
            ], 404);
        }

        $labRequest->load(['patient', 'visit', 'samples']);

        return response()->json([
            'success' => true,
            'lab_request' => $labRequest,
            'patient' => $labRequest->patient,
            'visit' => $labRequest->visit,
            'samples' => $labRequest->samples,
        ]);
    }

    /**
     * Generate next sample ID for a lab request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateNextSampleId(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lab_no' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid lab number format',
                'errors' => $validator->errors(),
            ], 422);
        }

        $labNo = $request->input('lab_no');
        $sampleId = $this->barcodeService->generateNextSampleId($labNo);

        return response()->json([
            'success' => true,
            'lab_no' => $labNo,
            'next_sample_id' => $sampleId,
        ]);
    }

    /**
     * Test barcode generation for a given text.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testGenerate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid text format',
                'errors' => $validator->errors(),
            ], 422);
        }

        $text = $request->input('text');
        
        try {
            $barcode = $this->barcodeService->generateReceiptBarcode($text);
            
            return response()->json([
                'success' => true,
                'text' => $text,
                'barcode' => $barcode,
                'barcode_length' => strlen($barcode),
            ]);
        } catch (\Exception $e) {
            Log::error('Barcode generation test failed', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate barcode: ' . $e->getMessage(),
                'text' => $text,
            ], 500);
        }
    }
}