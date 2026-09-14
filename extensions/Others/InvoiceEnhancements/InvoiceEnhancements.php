<?php

namespace Paymenter\Extensions\Others\InvoiceEnhancements;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Events\Invoice\GeneratePdf;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;

/**
 * Supplies the invoice PDF instead of editing Paymenter's own template
 * directly, so this survives Paymenter's own updater — which wipes and
 * replaces the whole app/ directory, including resources/views/pdf, on
 * every update. Living in extensions/ (never touched by that process)
 * is what makes this change permanent instead of something to reapply
 * after every release.
 */
#[ExtensionMeta(
    name: 'Invoice Enhancements',
    description: 'Shows the applied coupon code on invoice PDFs.',
    version: '1.0.0',
    author: 'Hostorio',
)]
class InvoiceEnhancements extends Extension
{
    public function boot()
    {
        View::addNamespace('invoiceenhancements', __DIR__ . '/resources/views');

        Event::listen(GeneratePdf::class, function (GeneratePdf $event) {
            $event->setPdf(DomPDF::loadView('invoiceenhancements::pdf.invoice', ['invoice' => $event->invoice]));
        });
    }
}
