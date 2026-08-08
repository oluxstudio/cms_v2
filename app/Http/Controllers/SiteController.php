<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Page;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SiteController extends Controller
{
    /**
     * Generate the site's static bundle to the chosen destination
     * (server folder, ZIP download, or a connected GitHub repo).
     */
    /** Legacy static-site generation was removed with the wireframe system. */
    public function generate(Request $request, $siteID)
    {
        $site = $this->findSiteBySlug($siteID);
        abort_unless($site->canManageTeam(Auth::user()), 403);

        return redirect(url($site->name.'/templates'))
            ->with('generate_err', 'Static export has been rebuilt around the block builder — use Export HTML in the Build screen.');
    }

    public function findSiteByID($id)
    {
        $site = Site::findOrFail($id);
        abort_unless($site->accessibleBy(Auth::user()), 403);

        return $site;
    }

    public function findSiteBySlug($slug)
    {
        $site = Site::where('name', $slug)->firstOrFail();
        abort_unless($site->accessibleBy(Auth::user()), 403);

        return $site;
    }

    public function publish($siteID)
    {
        return view('publish', ['site' => $this->findSiteBySlug($siteID)]);
    }

    public function apiDocs($siteID)
    {
        return view('api-docs', ['site' => $this->findSiteBySlug($siteID)]);
    }

    public function apiKeys($siteID)
    {
        $site = $this->findSiteBySlug($siteID);
        abort_unless($site->canManageTeam(Auth::user()), 403);

        return view('api-keys', compact('site'));
    }

    public function alerts($siteID)
    {
        return view('alerts', ['site' => $this->findSiteBySlug($siteID)]);
    }

    public function messagesPage($siteID)
    {
        return view('messages', ['site' => $this->findSiteBySlug($siteID)]);
    }

    public function todosPage($siteID)
    {
        return view('todos', ['site' => $this->findSiteBySlug($siteID)]);
    }

    /**
     * Display the main site view.
     *
     * @return View
     */
    public function index()
    {
        return view('site');
    }

    public function templates()
    {
        return view('templates');
    }

    public function dashboard($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('dashboard', ['site' => $site]);
    }

    public function pages($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('pages', ['site' => $site]);
    }

    public function collections($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('collections', ['site' => $site]);
    }

    public function media($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('media', ['site' => $site]);
    }

    public function analytics($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('analytics', ['site' => $site]);
    }

    public function siteTemplates($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('site-templates', compact('site'));
    }

    public function marketplace($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('marketplace', compact('site'));
    }

    public function team($siteID)
    {
        $site = $this->findSiteBySlug($siteID);
        abort_unless($site->canManageTeam(Auth::user()), 403);

        return view('team', compact('site'));
    }

    public function contacts($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('contacts', compact('site'));
    }

    public function store($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('store', compact('site'));
    }

    public function orders($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('orders', compact('site'));
    }

    public function bookings($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('bookings', compact('site'));
    }

    public function posts($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('posts', compact('site'));
    }

    public function estimates($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('estimates', compact('site'));
    }

    public function donations($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('donations', compact('site'));
    }

    public function invoices($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('invoices', compact('site'));
    }

    /** Dedicated page for ONE invoice (booking-card design + PDF download). */
    public function invoiceShow($siteID, int $invoice)
    {
        $site = $this->findSiteBySlug($siteID);
        $invoice = Invoice::where('site_id', $site->id)->findOrFail($invoice);

        return view('invoice-show', compact('site', 'invoice'));
    }

    /** Letterhead-style PDF of the invoice. */
    public function invoicePdf($siteID, int $invoice)
    {
        $site = $this->findSiteBySlug($siteID);
        $invoice = Invoice::where('site_id', $site->id)->findOrFail($invoice);

        return Pdf::loadView('pdf.invoice', compact('site', 'invoice'))
            ->setPaper('a4')
            ->download("{$invoice->number}.pdf");
    }

    public function submissions($siteID)
    {
        $site = $this->findSiteBySlug($siteID);
        // Template moderation is platform-admin machinery, not a site page.
        abort_unless(in_array(Auth::user()?->email, (array) config('templates.moderators'), true), 403);

        return view('submissions', compact('site'));
    }

    public function forms($siteID)
    {
        $site = $this->findSiteBySlug($siteID);

        return view('forms', compact('site'));
    }

    public function sites()
    {
        dd('site');

        return view('selected-site');
    }
}
