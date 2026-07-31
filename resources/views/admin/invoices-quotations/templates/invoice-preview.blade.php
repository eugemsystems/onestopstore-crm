<div style="border: 1px solid #ddd; background: #f5f5f5; padding: 20px; max-height: 800px; overflow-y: auto;">
    @php
        $document = App\Models\InvoiceQuotation::with(['items', 'user', 'creator'])->findOrFail($id);
        $template = match($document->document_type) {
            'invoice' => 'invoice',
            'quotation' => 'quotation',
            'receipt' => 'receipt',
            'proforma' => 'proforma',
            'delivery_note' => 'delivery_note',
            default => 'invoice',
        };
    @endphp
    @include("admin.invoices-quotations.templates.{$template}", ['document' => $document])
</div>
