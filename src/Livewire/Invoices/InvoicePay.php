<?php

namespace VentureDrake\LaravelCrm\Livewire\Invoices;

use Carbon\Carbon;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Invoice;

class InvoicePay extends Component
{
    use Toast;

    public bool $showPayInvoice = false;

    public $invoice;

    public $amount;

    public $amount_paid;

    public $amount_due;

    /**
     * Returns validation rules.
     *
     * @return array
     */
    protected function rules()
    {
        return [
            'amount' => 'required|numeric|size:'.($this->invoice->amount_due / 100),
        ];
    }

    public function mount(Invoice $invoice)
    {
        $this->invoice = $invoice;

        $this->amount_due = $invoice->amount_due / 100;
        $this->amount_paid = $invoice->amount_paid / 100;

        if ($this->amount_due > 0) {
            $this->amount = $this->amount_due;
        }
    }

    public function pay()
    {
        // Input "amount" pakai x-mask:dynamic="$money($input)" di blade
        // (invoice-pay.blade.php) buat format tampilan angkanya (mis.
        // "2,000.00"), tapi karena wire:model nempel langsung ke input yang
        // sama, Livewire ikutan nerima STRING BERFORMAT itu, bukan angka
        // bersih — bikin validasi 'numeric'/'size' gagal walau nilainya
        // kelihatan sama persis kayak yang di-auto-isi. Bersihkan dulu
        // sebelum divalidasi.
        if (is_string($this->amount)) {
            $this->amount = $this->parseMaskedAmount($this->amount);
        }

        $this->validate();

        $this->amount_due = ($this->invoice->amount_due / 100) - ($this->amount + ($this->invoice->amount_paid / 100));

        $this->invoice->update([
            'amount_paid' => $this->amount + ($this->invoice->amount_paid / 100),
            'amount_due' => $this->amount_due,
            'fully_paid_at' => (($this->amount_due == 0) ? Carbon::now() : null),
        ]);

        $this->success(
            ucfirst(trans('laravel-crm::lang.invoice_paid'))
        );

        $this->resetFields();

        $this->showPayInvoice = false;
    }

    /**
     * Ubah string uang berformat (mis. "2,000.00" atau "2.000,00") jadi
     * float bersih. Nyari pemisah desimal dari titik/koma TERAKHIR yang
     * diikuti 1-2 digit di ujung string — sisanya (pemisah ribuan) dibuang.
     */
    private function parseMaskedAmount(string $value): float
    {
        $value = trim(preg_replace('/[^0-9.,]/', '', $value));

        if ($value === '') {
            return 0.0;
        }

        if (preg_match('/^(.*)[.,](\d{1,2})$/', $value, $matches)) {
            $integerPart = preg_replace('/[^0-9]/', '', $matches[1]);
            $decimalPart = $matches[2];

            return (float) ($integerPart.'.'.$decimalPart);
        }

        return (float) preg_replace('/[^0-9]/', '', $value);
    }

    private function resetFields()
    {
        $this->reset('amount');
    }

    public function render()
    {
        return view('laravel-crm::livewire.invoices.invoice-pay');
    }
}
