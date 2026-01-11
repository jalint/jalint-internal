<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferDocument;
use Illuminate\Http\Request;

class OfferDocumentController extends Controller
{
    public function uploadByAdmin(Request $request, Offer $offer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $path = $request->file('file')->store('offer-documents', 'public');

        OfferDocument::create([
            'offer_id' => $offer->id,
            'type' => 'subkon_letter',
            'uploaded_by_role' => 'admin_kuptdk',
            'file_path' => $path,
        ]);

        return response()->json([
            'message' => 'Dokumen subkon berhasil diupload',
        ]);
    }

    public function uploadByCustomer(Request $request, Offer $offer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $path = $request->file('file')->store('offer-documents', 'public');

        OfferDocument::create([
            'offer_id' => $offer->id,
            'type' => 'subkon_letter',
            'uploaded_by_role' => 'customer',
            'file_path' => $path,
        ]);

        return response()->json([
            'message' => 'Dokumen bertandatangan berhasil diupload',
        ]);
    }
}
