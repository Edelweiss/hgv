<?php

namespace App\Dto;

/**
 * Value object for a single HGV record extracted from the BaseX XML database.
 *
 * Getter names use consistent English, aligned with the TEI/XML element names
 * used in the BaseX XQuery layer. Twig templates resolve e.g. `record.dating`
 * via `getDating()`.
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
    public function getTm(): ?string    { return $this->data['tm']  ?? null; }

    /** Letter suffix extracted from the HGV filename, e.g. 'b' for '79242b'. */
    public function getLetterSuffix(): string
    {
        return preg_replace('/\d/', '', $this->data['id'] ?? '') ?: '';
    }

    // ── Publication ──────────────────────────────────────────────────────────────

    public function getPublication(): ?string       { return $this->data['pubAbbr']  ?? null; }
    public function getVolume(): ?string            { return $this->data['pubVol']   ?? null; }
    public function getNumber(): ?string            { return $this->data['pubNr']    ?? null; }

    /** Formatted publication label, e.g. "BGU 1 112". */
    public function getPublicationFull(): ?string   { return $this->data['publ']     ?? null; }

    /** Other publication references joined by '; '. */
    public function getOtherPublications(): ?string { return $this->data['otherPub'] ?? null; }

    // ── Description ──────────────────────────────────────────────────────────────

    public function getTitle(): ?string             { return $this->data['title']        ?? null; }
    public function getDating(): ?string            { return $this->data['dating']       ?? null; }
    public function getPlace(): ?string             { return $this->data['place']        ?? null; }
    public function getMaterial(): ?string           { return $this->data['material']     ?? null; }
    /** Keywords from hgv scheme, joined by '; '. */
    public function getKeywords(): ?string           { return $this->data['keywords']     ?? null; }
    public function getCommentary(): ?string         { return $this->data['commentary']   ?? null; }
    /** Illustration references (plain text). */
    public function getIllustrations(): ?string      { return $this->data['illustrations'] ?? null; }
    /**
     * Translations string formatted for processTranslations() Twig function,
     * e.g. "Deutsch: Erman - Krebs, ... ; Englisch: ..."
     */
    public function getTranslations(): ?string       { return $this->data['translations']  ?? null; }
    /** Provenance places joined by ' - '. */
    public function getProvenance(): ?string         { return $this->data['provenance']    ?? null; }
    /** Inventory number. */
    public function getInventoryNumber(): ?string    { return $this->data['invNo']         ?? null; }

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

    /** HTML translation rendered from the EpiDoc translation file on the filesystem, or null. */
    public function getHtmlTranslation(): ?string
    {
        $id = $this->getId();
        if ($id === null) {
            return null;
        }
        $file = '/mnt/sds_cifs/idp.data/papyri/aquila/HGV_trans_EpiDoc_HTML/' . $id . '.html';
        return file_exists($file) ? file_get_contents($file) : null;
    }

    // ── Date helpers ──────────────────────────────────────────────────────────────

    public function getNotBefore(): ?int
    {
        $v = $this->data['notBefore'] ?? '';
        return ($v !== '' && is_numeric($v)) ? (int)$v : null;
    }

    public function getNotAfter(): ?int
    {
        $v = $this->data['notAfter'] ?? '';
        return ($v !== '' && is_numeric($v)) ? (int)$v : null;
    }

    // ── Mentioned Dates ───────────────────────────────────────────────────────────

    /** Raw text of the mentionedDates original note, e.g. "Z. 17: 58 - 59; Z. 21: 59 - 60". */
    public function getMentionedDatesText(): ?string { return $this->data['mentionedDatesText'] ?? null; }

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

    // ── Raw data access ───────────────────────────────────────────────────────────

    /** Return underlying data array (e.g. for DataTables JSON response). */
    public function toArray(): array   { return $this->data; }
}
