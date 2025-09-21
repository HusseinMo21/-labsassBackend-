<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
        @page {
            margin: 0;
        }
        
        /* Ensure consistent spacing on all pages */
        .main-content {
            padding: 40px;
            padding-top: 125px;
            padding-bottom: 140px;
            position: relative;
            z-index: 1;
        }
        
        /* Force page breaks with consistent spacing */
        .force-page-break {
            page-break-before: always;
            break-before: page;
            margin-top: 150px !important;
            padding-top: 150px !important;
            height: 150px !important;
        }
        
        /* Style for repeated table headers on new pages */
        .table-header-repeat th {
            background-color: transparent;
            padding: 6px 4px;
            text-align: center;
            border: 1px solid #333;
            font-weight: bold;
            font-size: 10px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            border-top: 2px solid #333;
        }
        
        /* Style for repeated table on new pages */
        .table-header-repeat {
            border-top: 2px solid #333;
            border-left: 2px solid #333;
            border-right: 2px solid #333;
        }
        
        /* Style for the main table header */
        .table-header {
            border-top: 2px solid #333;
            border-left: 2px solid #333;
            border-right: 2px solid #333;
        }
        .table-header th {
            background-color: transparent;
            padding: 6px 4px;
            text-align: center;
            border: 1px solid #333;
            font-weight: bold;
            font-size: 10px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            border-top: 2px solid #333;
        }
        
        /* Ensure table rows don't split */
        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
      
        body {
            font-family: Arial, sans-serif;
            padding: 0;
            margin: 0;
            line-height: 1.3;
            font-size: 12px;
            position: relative;
        }
        
        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            object-fit: cover;
        }
        
        .invoice-header-box {
            text-align: center;
            margin: 10px 0 10px 0;
            border: 2px solid #333;
            border-radius: 6px;
            padding: 0px 8px 0px 8px;
            background-color: transparent;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            display: flex !important;
            flex-direction: row !important;
            justify-content: center !important;
            align-items: flex-start !important;
            gap: 10px !important;
            /* Force horizontal layout - title and order number side by side */
        }
        
        /* Override any conflicting flex-direction rules */
        .invoice-header-box * {
            flex-direction: inherit !important;
        }
        
        .title-box {
            background-color: #f8f9fa;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 8px 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 150px;
        }
        
        .title-box h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-shadow: 2px 2px 4px rgba(255, 255, 255, 0.8);
        }
        
        .order-number-box {
            background-color: #f8f9fa;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 3px 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 180px;
            width: fit-content;
        }
        
        .order-number-box .order-number {
            margin: 0;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            text-shadow: 2px 2px 4px rgba(255, 255, 255, 0.8);
        }
        
        /* Info section with 2 items on left, 2 on right */
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .info-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .info-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: right;
        }
        
        .info-item {
            font-size: 12px;
            color: #333;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .info-item strong {
            font-weight: bold;
        }
        
        .small-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 3px 10px;
            margin: 5px 0;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        

        
        .info-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .info-item {
            flex: 1;    
            text-align: center;
            min-width: 0;
            white-space: nowrap;
            display: inline-block;
            line-height: 1.2;
            margin: 0 75px;
        }
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #333;
        }
        .info-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        .info-box p {
            margin: 3px 0;
            font-size: 12px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        .info-box strong {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
            background-color: transparent;
            border-radius: 5px;
            overflow: hidden;
            page-break-inside: auto;
        }
        th, td {
            border: 1px solid #333;
            padding: 5px 3px;
            text-align: left;
            vertical-align: top;
            background-color: transparent;
        }
        th {
            background-color: transparent;
            font-weight: bold;
            text-align: center;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            font-size: 12px;
            border-top: 2px solid #333;
        }
        td {
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        tbody {
    page-break-inside: auto;
        }
        .signature-section {
            margin-top: 30px;
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .signature-box {
            margin-right: 50px;
            text-align: left;
            background-color: transparent;
            padding: 0;
            border: none;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 20px;
            padding-top: 5px;
            font-weight: bold;
            font-size: 12px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            width: 150px;
        }
        .content {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .content-area {
            flex: 1;
        }
        .footer {
            margin-top: auto;
            padding-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #333;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        /* Print Specific Styles */
        @media print {
            .main-content {
                padding: 40px;
            }
        }
    </style>
</head>
<body>
    @if(!empty($backgroundImage))
    <img src="data:image/jpeg;base64,{{ $backgroundImage }}" alt="Background" class="background-image">
    @endif
    
    <div class="main-content">
        <div class="invoice-header-box" style="margin: 20px 0; padding: 0px;">
            <table style="width: 100%; border: 4px solid #000; border-collapse: collapse; margin-bottom: 0px; background-color: transparent;">
                <tr>
                    <td colspan="3" style="padding: 8px 12px; font-size: 20px; border: 1px solid #000; text-align: center; font-weight: bold; background-color: #f5f5f5;">
                        <span style="margin-left: 30px;">Invoice</span> <span style="font-size: 10px; margin-left: 10px;">{{ $order->order_number }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px 12px; font-size: 14px; border: 1px solid #000; text-align: left; line-height: 1.2;">
                        <strong>Ship Name:</strong> {{ $order->ship->name }}
                    </td>
                    <td style="padding: 2px 12px; font-size: 14px; border: 1px solid #000; text-align: center; line-height: 1.2;">
                        <strong>Port:</strong> {{ $supplyOrder->port_name ?? ($order->port_name ?? 'N/A') }}
                    </td>
                    <td style="padding: 2px 12px; font-size: 14px; border: 1px solid #000; text-align: center; line-height: 1.2;">
                        <strong>Date:</strong> {{ $order->ship->order_date ? \Carbon\Carbon::parse($order->ship->order_date)->format('d/m/Y') : $order->created_at->format('d/m/Y') }}
                    </td>
                </tr>
                @if($order->po_number || $order->po_agency)
                <tr>
                    @if($order->po_number)
                        <td style="padding: 2px 12px; font-size: 14px; border: 1px solid #000; text-align: left; line-height: 1.2;">
                            <strong>PO NO:</strong> {{ $order->po_number }}
                        </td>
                    @else
                        <td style="padding: 2px 12px; font-size: 14px; border: 1px solid #000; text-align: left; line-height: 1.2;">
                        </td>
                    @endif
                    @if($order->po_agency)
                        <td style="padding: 2px 12px; font-size: 14px; border: 1px solid #000; text-align: left; line-height: 1.2;">
                            <strong>Agency:</strong> {{ $order->po_agency }}
                        </td>
                    @else
                        <td style="padding: 2px 12px; font-size: 14px; border: 1px solid #000; text-align: left; line-height: 1.2;">
                        </td>
                    @endif
                    <td style="padding: 2px 12px; font-size: 14px; border: 1px solid #000; text-align: left; line-height: 1.2;">
                    </td>
                </tr>
                @endif
            </table>
        </div>

        @if($items->count() > 0)
            <table style="border-collapse: collapse; line-height: 1.2;">
                <tbody>
                    <tr class="table-header" style="line-height: 1.2 !important;">
                        <th style="padding: 2px !important; line-height: 1.2 !important;">N.O</th>
                        <th style="padding: 2px !important; line-height: 1.2 !important;">N.O Code</th>
                        <th style="padding: 2px !important; line-height: 1.2 !important;">Item</th>
                        <th style="padding: 2px !important; line-height: 1.2 !important;">Unit</th>
                        <th style="padding: 2px !important; line-height: 1.2 !important;">Price</th>
                        <th style="padding: 2px !important; line-height: 1.2 !important;">Quantity</th>
                        <th style="padding: 2px !important; line-height: 1.2 !important;">Total</th>
                    </tr>
                    @php
                        $firstPageItems = 34; // First page can now take 34 items
                        $otherPageItems = 35; // Other pages can take 35 items
                        $grandTotal = 0;
                    @endphp
                    @foreach($items as $index => $item)
                        @if($index == $firstPageItems)
                            <tr class="force-page-break">
                                <td colspan="7" style="padding-top: 150px; border: none;"></td>
                            </tr>
                            <tr class="table-header-repeat">
                                <th>N.O</th>
                                <th>N.O Code</th>
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        @elseif($index > $firstPageItems && ($index - $firstPageItems) % $otherPageItems == 0)
                            <tr class="force-page-break">
                                <td colspan="7" style="padding-top: 150px; border: none;"></td>
                            </tr>
                            <tr class="table-header-repeat">
                                <th>N.O</th>
                                <th>N.O Code</th>
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        @endif
                        
                        @php
                            $itemPrice = $item->unit_price ?? 0;
                            $itemTotal = $itemPrice * $item->quantity;
                            $grandTotal += $itemTotal;
                        @endphp
                        <tr style="line-height: 1.2 !important;">
                            <td style="padding: 2px !important; line-height: 1.2 !important; text-align: center;">{{ $index + 1 }}</td>
                            <td style="padding: 2px !important; line-height: 1.2 !important; text-align: center;">{{ $item->serial_code ?? 'N/A' }}</td>
                            <td style="padding: 2px !important; line-height: 1.2 !important; text-align: center;">{{ $item->provisionItem->item_name ?? $item->item_name ?? 'Unknown Item' }}</td>
                            <td style="padding: 2px !important; line-height: 1.2 !important; text-align: center;">{{ $item->provisionItem->unit ?? $item->unit ?? 'N/A' }}</td>
                            <td style="padding: 2px !important; line-height: 1.2 !important; text-align: center;">{{ number_format($itemPrice, 2) }}</td>
                            <td style="padding: 2px !important; line-height: 1.2 !important; text-align: center;">{{ number_format($item->quantity, 0) }}</td>
                            <td style="padding: 2px !important; line-height: 1.2 !important; text-align: center;">{{ number_format($itemTotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid #333;">
                        <td colspan="6" style="text-align: right; font-weight: bold; padding: 4px; text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);">Total ({{ $order->currency ?? 'USD' }}):</td>
                        <td style="font-weight: bold; padding: 4px; text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);">{{ number_format($grandTotal, 2) }}</td>
                    </tr>
                    @if($order->discount_percentage && $order->discount_percentage > 0)
                        @php
                            $discountAmount = ($grandTotal * $order->discount_percentage) / 100;
                            $finalTotal = $grandTotal - $discountAmount;
                        @endphp
                        <tr>
                            <td colspan="6" style="text-align: right; font-weight: bold; padding: 4px; color: #d32f2f; text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);">Discount ({{ number_format($order->discount_percentage, 0) }}%):</td>
                            <td style="font-weight: bold; padding: 4px; color: #d32f2f; text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);">-{{ number_format($discountAmount, 2) }}</td>
                        </tr>
                        <tr style="border-top: 1px solid #333;">
                            <td colspan="6" style="text-align: right; font-weight: bold; padding: 4px; text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);">Total After Discount (USD):</td>
                            <td style="font-weight: bold; padding: 4px; text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);">{{ number_format($finalTotal, 2) }}</td>
                        </tr>
                    @endif
                </tfoot>
            </table>
        @else
            <p style="text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);">No items found.</p>
        @endif

        <div class="signature-section">
            <div class="signature-box">
                <div style="text-align: left; margin-top: 20px; font-size: 14px;">
                    <strong>Master Signature</strong><br>
                    {{ $order->vessel_type ?? 'MV' }}: {{ $order->ship->name }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>