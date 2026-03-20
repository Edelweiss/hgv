<?php

namespace App\Dto;

/**
 * Value object for a single HGV record extracted from the BaseX XML database.
 *
 * Provides getters that are compatible with the existing Twig templates
 * (browse/_datasheet.html.twig, browse/single.html.twig, shortcut/shortcut.html.twig).
 * Twig resolves `record.someField` via `getSomeField()` / `someField()` / `isSomeField()`.
 */
class HgvRecord
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // ── Identifiers ──────────────────────────────────────────────────────────────

    public function getId(): ?string    { return $this->data['id']  ?? null; }
    public function getHgvId(): ?string { return $this->data['id']  ?? null; }
    public function getTmNr(): ?string  { return $this->data['tm']  ?? null; }

    /** Letter suffix extracted from the HGV filename, e.g. 'b' for '79242b'. */
    public function getTexLett(): string
    {
        return preg_replace('/\d/', '', $this->data['id'] ?? '') ?: '';
    }

    // ── Publication ──────────────────────────────────────────────────────────────

    public function getPublikation(): ?string      { return $this->data['pubAbbr']  ?? null; }
    public function getBand(): ?string             { return $this->data['pubVol']   ?? null; }
    public function getNummer(): ?string           { return $this->data['pubNr']    ?? null; }

    /** Formatted publication label, e.g. "BGU 1 112". */
    public function getPublikationLang(): ?string  { return $this->data['publ']     ?? null; }

    /** Other publication references joined by '; '. */
    public function getAnderePublikation(): ?string { return $this->data['otherPub'] ?? null; }

    // ── Description ──────────────────────────────────────────────────────────────

    public function getOriginaltitel(): ?string     { return $this->data['title']        ?? null; }
    /** For the browse table this is the same as originaltitel (no pre-rendered HTML). */
    public function getOriginaltitelHtml(): ?string { return $this->data['title']        ?? null; }
    public function getDatierungIi(): ?string       { return $this->data['dating']       ?? null; }
    public function getOrt(): ?string               { return $this->data['place']        ?? null; }
    public function getMaterial(): ?string          { return $this->data['material']     ?? null; }
    /** Keywords from hgv scheme, joined by '; '. */
    public function getInhalt(): ?string            { return $this->data['keywords']     ?? null; }
    public function getInhaltHtml(): ?string        { return $this->data['keywords']     ?? null; }
    public function getBemerkungen(): ?string       { return $this->data['commentary']   ?? null; }
    /** Illustration references (plain text). */
    public function getAbbildung(): ?string         { return $this->data['illustrations'] ?? null; }
    /**
     * Translations string formatted for processTranslations() Twig function,
     * e.g. "Deutsch: Erman - Krebs, ... ; Englisch: ..."
     */
    public function getUebersetzungen(): ?string    { return $this->data['translations']  ?? null; }
    /** Provenance places joined by ' - '. */
    public function getProvenance(): ?string        { return $this->data['provenance']    ?? null; }
    /** Inventory number. */
    public function getInvNr(): ?string             { return $this->data['invNo']         ?? null; }

    // ── BL Corrections ───────────────────────────────────────────────────────────

    /** BL entries text, e.g. "BL VIII, S. 2; BL X, S. 5". */
    public function getBl(): ?string  { return $this->data['bl']       ?? null; }
    /** True when a BL-online entry exists; url is derived from hgvId in the template. */
    public function getBlOnline(): bool { return !empty($this->data['blOnline']); }

    // ── DDB Link ─────────────────────────────────────────────────────────────────

    public function getDdbSer(): ?string { return $this->data['ddbSer'] ?? null; }
    public function getDdbVol(): ?string { return $this->data['ddbVol'] ?? null; }
    public function getDdbDoc(): ?string { return $this->data['ddbDoc'] ?? null; }

    /** True when DDB text was found and extracted from the BaseX ddb database. */
    public function hasHtmlDdb(): bool   { return !empty($this->data['ddbText']); }
    /** Basic rendition of the DDB transcription (line-numbered plain text as HTML). */
    public function getHtmlDdb(): string { return $this->data['ddbText'] ?? ''; }

    // ── Date helpers (used by the search form / sort) ─────────────────────────────

    public function getChronMinimum(): ?int
    {
        $v = $this->data['notBefore'] ?? '';
        return ($v !== '' && is_numeric($v)) ? (int)$v : null;
    }

    public function getChronMaximum(): ?int
    {
        $v = $this->data['notAfter'] ?? '';
        return ($v !== '' && is_numeric($v)) ? (int)$v : null;
    }

    // ── Mentioned Dates ───────────────────────────────────────────────────────────

    /** Raw text of the mentionedDates original note, e.g. "Z. 17: 58 - 59; Z. 21: 59 - 60". */
    public function getErwaehnteDaten(): ?string { return $this->data['erwaehnteDaten'] ?? null; }

    /**
     * Structured mentioned dates.
     * @return array<int,array{zeile: string, datierung: string}>
     */
    public function getMentionedDates(): array { return $this->data['mentionedDates'] ?? []; }

    // ── Picture Links ─────────────────────────────────────────────────────────────

    /**
     * Picture links extracted from HGV figure/graphic elements.
     * @return array<int,array{url: string, institution: string}>
     */
    public function getPictureLinks(): array { return $this->data['pictureLinks'] ?? []; }

    // ── Feature flags without XML equivalent (kept for template compatibility) ────

    /** LDAB flag – not encoded in the HGV XML; always null. */
    public function getLdab(): ?string { return null; }
    /** DAHT flag – not encoded in the HGV XML; always null. */
    public function getDaht(): ?string { return null; }
    /** DFG flag – not encoded in the HGV XML; always null. */
    public function getDfg(): ?string  { return null; }

    // ── Raw data access ───────────────────────────────────────────────────────────

    /** Return underlying data array (e.g. for DataTables JSON response). */
    public function toArray(): array   { return $this->data; }
}
