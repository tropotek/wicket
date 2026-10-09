<?php

namespace App\Support;

use App\Html\Pipeline;
use App\Html\RenderContext;
use App\Models\Page;
use App\Models\User;
use Dom\HTMLDocument;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * A page as a PDF (dompdf). Content goes through the render pipeline for PDF output, then images
 * are pointed at local media files — dompdf never fetches anything over the network.
 */
class PdfExport
{
    public function __construct(private Pipeline $pipeline) {}

    public function pdf(Page $page, ?User $viewer): string
    {
        $dirs = storage_path('app/dompdf');
        if (! is_dir($dirs)) {
            mkdir($dirs, 0775, true);
        }

        $dompdf = new Dompdf(new Options([
            'isRemoteEnabled' => false,
            'isJavascriptEnabled' => false,
            'isPhpEnabled' => false,
            'chroot' => [base_path('data/media')],
            'fontDir' => $dirs,
            'fontCache' => $dirs,
            'tempDir' => $dirs,
            'defaultFont' => 'DejaVu Sans',
        ]));
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view('pages.pdf', ['page' => $page, 'html' => $this->html($page, $viewer)])->render());
        $dompdf->render();

        $footer = config('app.name').' · '.__('Page {PAGE_NUM} of {PAGE_COUNT}');
        $dompdf->getCanvas()->page_text(36, 810, $footer, $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.45, 0.45, 0.45]);

        return (string) $dompdf->output();
    }

    /** The page's content as dompdf will see it. */
    public function html(Page $page, ?User $viewer): string
    {
        $html = $this->pipeline->render((string) $page->latestContent?->html, new RenderContext($page, $viewer, 'pdf'));

        if ($html === '') {
            return '';
        }

        $doc = HTMLDocument::createFromString('<!doctype html><html><body>'.$html, LIBXML_NOERROR, 'UTF-8');

        foreach (iterator_to_array($doc->body->querySelectorAll('img')) as $img) {
            $file = $this->mediaFile((string) $img->getAttribute('src'));
            if ($file !== null) {
                $img->setAttribute('src', $file);

                continue;
            }
            $alt = trim((string) $img->getAttribute('alt'));
            $img->replaceWith($doc->createTextNode($alt !== '' ? "[{$alt}]" : '['.__('image').']'));
        }

        foreach (iterator_to_array($doc->body->querySelectorAll('iframe')) as $frame) {
            $link = $doc->createElement('a');
            $link->setAttribute('href', (string) $frame->getAttribute('src'));
            $link->textContent = '['.__('Video').': '.$frame->getAttribute('src').']';
            $frame->replaceWith($link);
        }

        foreach ($doc->body->querySelectorAll('a[href^="/"]:not([href^="//"])') as $a) {
            $a->setAttribute('href', url($a->getAttribute('href')));
        }

        return $doc->body->innerHTML;
    }

    /** Absolute path of a /data/media URL's file, if it's really inside the media root. */
    private function mediaFile(string $src): ?string
    {
        $path = parse_url($src, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, MediaStore::URL.'/')) {
            return null;
        }

        $root = realpath(base_path('data/media'));
        $file = realpath(base_path('data/media').'/'.rawurldecode(substr($path, strlen(MediaStore::URL) + 1)));

        return $file !== false && $root !== false && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file) ? $file : null;
    }
}
