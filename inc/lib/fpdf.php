<?php
/**
 * FPDF v1.86 — Lightweight Pure PHP PDF Generator
 * Customized for Evonee Quote System PDF Generation.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FPDF')) {
    class FPDF {
        protected $page;
        protected $n;
        protected $offsets;
        protected $buffer;
        protected $pages;
        protected $state;
        protected $compress;
        protected $k;
        protected $DefOrientation;
        protected $CurOrientation;
        protected $StdPageSizes;
        protected $DefPageSize;
        protected $CurPageSize;
        protected $CurRotation;
        protected $PageInfo;
        protected $wPt, $hPt;
        protected $w, $h;
        protected $lMargin;
        protected $tMargin;
        protected $rMargin;
        protected $bMargin;
        protected $cMargin;
        protected $x, $y;
        protected $lasth;
        protected $LineWidth;
        protected $fontpath;
        protected $CoreFonts;
        protected $fonts;
        protected $FontFiles;
        protected $encodings;
        protected $cmaps;
        protected $FontFamily;
        protected $FontStyle;
        protected $underline;
        protected $CurrentFont;
        protected $FontSizePt;
        protected $FontSize;
        protected $DrawColor;
        protected $FillColor;
        protected $TextColor;
        protected $ColorFlag;
        protected $WithAlpha;
        protected $ws;
        protected $images;
        protected $PageLinks;
        protected $links;
        protected $AutoPageBreak;
        protected $PageBreakTrigger;
        protected $InHeader;
        protected $InFooter;
        protected $AliasNbPages;
        protected $PDFVersion;

        public function __construct($orientation='P', $unit='mm', $size='A4') {
            $this->_doinit($orientation, $unit, $size);
        }

        protected function _doinit($orientation, $unit, $size) {
            $this->page = 0;
            $this->n = 2;
            $this->buffer = '';
            $this->pages = array();
            $this->PageInfo = array();
            $this->state = 0;
            $this->fonts = array();
            $this->FontFiles = array();
            $this->encodings = array();
            $this->cmaps = array();
            $this->images = array();
            $this->links = array();
            $this->PageLinks = array();
            $this->InHeader = false;
            $this->InFooter = false;
            $this->lasth = 0;
            $this->FontFamily = '';
            $this->FontStyle = '';
            $this->FontSizePt = 12;
            $this->underline = false;
            $this->DrawColor = '0 G';
            $this->FillColor = '0 g';
            $this->TextColor = '0 g';
            $this->ColorFlag = false;
            $this->WithAlpha = false;
            $this->ws = 0;
            $this->CoreFonts = array('courier', 'helvetica', 'times', 'symbol', 'zapfdingbats');

            if ($unit === 'pt') $this->k = 1;
            elseif ($unit === 'mm') $this->k = 72/25.4;
            elseif ($unit === 'cm') $this->k = 72/2.54;
            elseif ($unit === 'in') $this->k = 72;
            else $this->Error('Incorrect unit: '.$unit);

            $this->StdPageSizes = array(
                'a3'=>array(841.89, 1190.55),
                'a4'=>array(595.28, 841.89),
                'a5'=>array(420.94, 595.28),
                'letter'=>array(612, 792),
                'legal'=>array(612, 1008)
            );
            $size = $this->_getpagesize($size);
            $this->DefPageSize = $size;
            $this->CurPageSize = $size;

            $orientation = strtolower($orientation);
            if ($orientation === 'p' || $orientation === 'portrait') {
                $this->DefOrientation = 'P';
                $this->w = $size[0];
                $this->h = $size[1];
            } elseif ($orientation === 'l' || $orientation === 'landscape') {
                $this->DefOrientation = 'L';
                $this->w = $size[1];
                $this->h = $size[0];
            } else $this->Error('Incorrect orientation: '.$orientation);

            $this->CurOrientation = $this->DefOrientation;
            $this->wPt = $this->w * $this->k;
            $this->hPt = $this->h * $this->k;
            $this->CurRotation = 0;

            $margin = 28.35/$this->k;
            $this->SetMargins($margin, $margin);
            $this->cMargin = $margin / 10;
            $this->LineWidth = .567/$this->k;
            $this->SetAutoPageBreak(true, 2 * $margin);
            $this->SetDisplayMode('default');
            $this->SetCompression(true);
            $this->PDFVersion = '1.3';
        }

        public function SetMargins($left, $top, $right=null) {
            $this->lMargin = $left;
            $this->tMargin = $top;
            if ($right === null) $right = $left;
            $this->rMargin = $right;
        }

        public function SetLeftMargin($margin) {
            $this->lMargin = $margin;
            if ($this->page > 0 && $this->x < $margin) $this->x = $margin;
        }

        public function SetTopMargin($margin) {
            $this->tMargin = $margin;
        }

        public function SetRightMargin($margin) {
            $this->rMargin = $margin;
        }

        public function SetAutoPageBreak($auto, $margin=0) {
            $this->AutoPageBreak = $auto;
            $this->bMargin = $margin;
            $this->PageBreakTrigger = $this->h - $margin;
        }

        public function SetDisplayMode($zoom, $layout='default') {
            if ($zoom === 'fullpage' || $zoom === 'fullwidth' || $zoom === 'real' || $zoom === 'default' || !is_string($zoom))
                $this->ZoomMode = $zoom;
            else $this->Error('Incorrect zoom display mode: '.$zoom);

            if ($layout === 'single' || $layout === 'continuous' || $layout === 'two' || $layout === 'default')
                $this->LayoutMode = $layout;
            else $this->Error('Incorrect layout display mode: '.$layout);
        }

        public function SetCompression($compress) {
            if (function_exists('gzcompress')) $this->compress = $compress;
            else $this->compress = false;
        }

        public function SetTitle($title, $isUTF8=false) {
            $this->title = $isUTF8 ? $title : utf8_encode($title);
        }

        public function SetSubject($subject, $isUTF8=false) {
            $this->subject = $isUTF8 ? $subject : utf8_encode($subject);
        }

        public function SetAuthor($author, $isUTF8=false) {
            $this->author = $isUTF8 ? $author : utf8_encode($author);
        }

        public function SetCreator($creator, $isUTF8=false) {
            $this->creator = $isUTF8 ? $creator : utf8_encode($creator);
        }

        public function AliasNbPages($alias='{nb}') {
            $this->AliasNbPages = $alias;
        }

        public function Error($msg) {
            throw new Exception('FPDF error: '.$msg);
        }

        public function Open() {
            $this->state = 1;
        }

        public function Close() {
            if ($this->state === 3) return;
            if ($this->page === 0) $this->AddPage();
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            $this->_endpage();
            $this->_enddoc();
        }

        public function AddPage($orientation='', $size='', $rotation=0) {
            if ($this->state === 0) $this->Open();
            $family = $this->FontFamily;
            $style = $this->FontStyle.($this->underline ? 'U' : '');
            $fontsize = $this->FontSizePt;
            $lw = $this->LineWidth;
            $dc = $this->DrawColor;
            $fc = $this->FillColor;
            $tc = $this->TextColor;
            $cf = $this->ColorFlag;

            if ($this->page > 0) {
                $this->InFooter = true;
                $this->Footer();
                $this->InFooter = false;
                $this->_endpage();
            }

            $this->_beginpage($orientation, $size, $rotation);
            $this->_out('2 J');
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.2F w', $lw*$this->k));

            if ($family) $this->SetFont($family, $style, $fontsize);

            $this->DrawColor = $dc;
            if ($dc !== '0 G') $this->_out($dc);

            $this->FillColor = $fc;
            if ($fc !== '0 g') $this->_out($fc);

            $this->TextColor = $tc;
            $this->ColorFlag = $cf;

            $this->InHeader = true;
            $this->Header();
            $this->InHeader = false;

            if ($this->LineWidth !== $lw) {
                $this->LineWidth = $lw;
                $this->_out(sprintf('%.2F w', $lw*$this->k));
            }

            if ($family) $this->SetFont($family, $style, $fontsize);
            if ($this->DrawColor !== $dc) {
                $this->DrawColor = $dc;
                $this->_out($dc);
            }
            if ($this->FillColor !== $fc) {
                $this->FillColor = $fc;
                $this->_out($fc);
            }
            $this->TextColor = $tc;
            $this->ColorFlag = $cf;
        }

        public function Header() {}
        public function Footer() {}

        public function PageNo() {
            return $this->page;
        }

        public function SetDrawColor($r, $g=null, $b=null) {
            if (($r === 0 && $g === 0 && $b === 0) || $g === null)
                $this->DrawColor = sprintf('%.3F G', $r/255);
            else
                $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r/255, $g/255, $b/255);
            if ($this->page > 0) $this->_out($this->DrawColor);
        }

        public function SetFillColor($r, $g=null, $b=null) {
            if (($r === 0 && $g === 0 && $b === 0) || $g === null)
                $this->FillColor = sprintf('%.3F g', $r/255);
            else
                $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);
            $this->ColorFlag = ($this->FillColor !== $this->TextColor);
            if ($this->page > 0) $this->_out($this->FillColor);
        }

        public function SetTextColor($r, $g=null, $b=null) {
            if (($r === 0 && $g === 0 && $b === 0) || $g === null)
                $this->TextColor = sprintf('%.3F g', $r/255);
            else
                $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);
            $this->ColorFlag = ($this->FillColor !== $this->TextColor);
        }

        public function GetStringWidth($s) {
            $s = (string)$s;
            $cw = &$this->CurrentFont['cw'];
            $w = 0;
            $l = strlen($s);
            for ($i = 0; $i < $l; $i++)
                $w += $cw[$s[$i]] ?? 600;
            return $w * $this->FontSize / 1000;
        }

        public function SetLineWidth($width) {
            $this->LineWidth = $width;
            if ($this->page > 0) $this->_out(sprintf('%.2F w', $width*$this->k));
        }

        public function Line($x1, $y1, $x2, $y2) {
            $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1*$this->k, ($this->h-$y1)*$this->k, $x2*$this->k, ($this->h-$y2)*$this->k));
        }

        public function Rect($x, $y, $w, $h, $style='') {
            if ($style === 'F') $op = 'f';
            elseif ($style === 'FD' || $style === 'DF') $op = 'B';
            else $op = 'S';
            $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s', $x*$this->k, ($this->h-$y)*$this->k, $w*$this->k, -$h*$this->k, $op));
        }

        public function SetFont($family, $style='', $size=0) {
            if ($family === '') $family = $this->FontFamily;
            else $family = strtolower($family);

            if ($family === 'arial') $family = 'helvetica';
            $style = strtoupper($style);

            if (strpos($style, 'U') !== false) {
                $this->underline = true;
                $style = str_replace('U', '', $style);
            } else $this->underline = false;

            if ($style === 'IB') $style = 'BI';
            if ($size == 0) $size = $this->FontSizePt;

            if ($this->FontFamily === $family && $this->FontStyle === $style && $this->FontSizePt == $size) return;

            $fontkey = $family.$style;
            if (!isset($this->fonts[$fontkey])) {
                if (in_array($family, $this->CoreFonts)) {
                    if ($family === 'symbol' || $family === 'zapfdingbats') $style = '';
                    $fontkey = $family.$style;
                    if (!isset($this->fonts[$fontkey])) {
                        $this->_loadfont($family, $style);
                    }
                } else $this->Error('Undefined font: '.$family.' '.$style);
            }

            $this->FontFamily = $family;
            $this->FontStyle = $style;
            $this->FontSizePt = $size;
            $this->FontSize = $size/$this->k;
            $this->CurrentFont = &$this->fonts[$fontkey];
            if ($this->page > 0)
                $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
        }

        protected function _loadfont($family, $style) {
            $fontkey = $family.$style;
            $name = '';
            if ($family === 'helvetica') {
                $name = 'Helvetica';
                if ($style === 'B') $name = 'Helvetica-Bold';
                if ($style === 'I') $name = 'Helvetica-Oblique';
                if ($style === 'BI') $name = 'Helvetica-BoldOblique';
            } elseif ($family === 'courier') {
                $name = 'Courier';
                if ($style === 'B') $name = 'Courier-Bold';
                if ($style === 'I') $name = 'Courier-Oblique';
                if ($style === 'BI') $name = 'Courier-BoldOblique';
            } elseif ($family === 'times') {
                $name = 'Times-Roman';
                if ($style === 'B') $name = 'Times-Bold';
                if ($style === 'I') $name = 'Times-Italic';
                if ($style === 'BI') $name = 'Times-BoldItalic';
            }

            $cw = array();
            for ($i = 0; $i <= 255; $i++) $cw[chr($i)] = 600;

            $i = count($this->fonts) + 1;
            $this->fonts[$fontkey] = array('i'=>$i, 'type'=>'core', 'name'=>$name, 'up'=>-100, 'ut'=>50, 'cw'=>$cw);
        }

        public function SetFontSize($size) {
            if ($this->FontSizePt == $size) return;
            $this->FontSizePt = $size;
            $this->FontSize = $size/$this->k;
            if ($this->page > 0)
                $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
        }

        public function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
            $k = $this->k;
            if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
                $x = $this->x;
                $ws = $this->ws;
                if ($ws > 0) {
                    $this->ws = 0;
                    $this->_out('0 Tw');
                }
                $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
                $this->x = $x;
                if ($ws > 0) {
                    $this->ws = $ws;
                    $this->_out(sprintf('%.3F Tw', $ws*$k));
                }
            }
            if ($w == 0) $w = $this->w - $this->rMargin - $this->x;

            $s = '';
            if ($fill || $border === 1) {
                if ($fill) $op = ($border === 1) ? 'B' : 'f';
                else $op = 'S';
                $s = sprintf('%.2F %.2F %.2F %.2F re %s ', $this->x*$k, ($this->h-$this->y)*$k, $w*$k, -$h*$k, $op);
            }

            if (is_string($border)) {
                $x = $this->x;
                $y = $this->y;
                if (strpos($border, 'L') !== false)
                    $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-$y)*$k, $x*$k, ($this->h-($y+$h))*$k);
                if (strpos($border, 'T') !== false)
                    $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-$y)*$k, ($x+$w)*$k, ($this->h-$y)*$k);
                if (strpos($border, 'R') !== false)
                    $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x+$w)*$k, ($this->h-$y)*$k, ($x+$w)*$k, ($this->h-($y+$h))*$k);
                if (strpos($border, 'B') !== false)
                    $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-($y+$h))*$k, ($x+$w)*$k, ($this->h-($y+$h))*$k);
            }

            if ($txt !== '') {
                $txt_clean = preg_replace('/[^\x20-\x7E\x0A]/', '', $txt);

                if ($align === 'R') $dx = $w - $this->cMargin - $this->GetStringWidth($txt_clean);
                elseif ($align === 'C') $dx = ($w - $this->GetStringWidth($txt_clean)) / 2;
                else $dx = $this->cMargin;

                if ($this->ColorFlag) $s .= 'q '.$this->TextColor.' ';
                $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x+$dx)*$k, ($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k, $this->_escape($txt_clean));

                if ($this->underline) $s .= ' '.$this->_dounderline($this->x+$dx, $this->y+.5*$h+.3*$this->FontSize, $txt_clean);
                if ($this->ColorFlag) $s .= ' Q';
            }

            if ($s) $this->_out($s);
            $this->lasth = $h;

            if ($ln > 0) {
                $this->y += $h;
                if ($ln === 1) $this->x = $this->lMargin;
            } else $this->x += $w;
        }

        public function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false) {
            $cw = &$this->CurrentFont['cw'];
            if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
            $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
            $s = str_replace("\r", '', (string)$txt);
            $nb = strlen($s);
            if ($nb > 0 && $s[$nb-1] === "\n") $nb--;
            $b = 0;
            if ($border) {
                if ($border === 1) {
                    $border = 'LRTB';
                    $b = 'LRT';
                    $b2 = 'LRB';
                } else {
                    $b2 = '';
                    if (strpos($border, 'L') !== false) $b2 .= 'L';
                    if (strpos($border, 'R') !== false) $b2 .= 'R';
                    if (strpos($border, 'B') !== false) $b2 .= 'B';
                    $b = strpos($border, 'T') !== false ? $b2.'T' : $b2;
                }
            }

            $sep = -1;
            $i = 0;
            $j = 0;
            $l = 0;
            $ns = 0;
            $nl = 1;

            while ($i < $nb) {
                $c = $s[$i];
                if ($c === "\n") {
                    $this->Cell($w, $h, substr($s, $j, $i-$j), $b, 2, $align, $fill);
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $ns = 0;
                    $nl++;
                    if ($border && $nl === 2) $b = $b2;
                    continue;
                }
                if ($c === ' ') {
                    $sep = $i;
                    $ns++;
                }
                $l += $cw[$c] ?? 600;
                if ($l > $wmax) {
                    if ($sep === -1) {
                        if ($i === $j) $i++;
                        $this->Cell($w, $h, substr($s, $j, $i-$j), $b, 2, $align, $fill);
                    } else {
                        $this->Cell($w, $h, substr($s, $j, $sep-$j), $b, 2, $align, $fill);
                        $i = $sep + 1;
                    }
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $ns = 0;
                    $nl++;
                    if ($border && $nl === 2) $b = $b2;
                } else $i++;
            }
            if ($i != $j) $this->Cell($w, $h, substr($s, $j, $i-$j), $b, 2, $align, $fill);
            $this->x = $this->lMargin;
        }

        public function Ln($h=null) {
            $this->x = $this->lMargin;
            if ($h === null) $this->y += $this->lasth;
            else $this->y += $h;
        }

        public function GetX() { return $this->x; }
        public function SetX($x) {
            if ($x >= 0) $this->x = $x;
            else $this->x = $this->w + $x;
        }

        public function GetY() { return $this->y; }
        public function SetY($y, $resetX=true) {
            if ($y >= 0) $this->y = $y;
            else $this->y = $this->h + $y;
            if ($resetX) $this->x = $this->lMargin;
        }

        public function SetXY($x, $y) {
            $this->SetY($y, false);
            $this->SetX($x);
        }

        public function Output($dest='', $name='', $isUTF8=false) {
            $this->Close();
            if ($dest === '') $dest = 'I';
            if ($name === '') $name = 'doc.pdf';

            switch (strtoupper($dest)) {
                case 'I':
                    $this->_checkoutput();
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="'.$name.'"');
                    echo $this->buffer;
                    break;
                case 'D':
                    $this->_checkoutput();
                    header('Content-Type: application/x-download');
                    header('Content-Disposition: attachment; filename="'.$name.'"');
                    echo $this->buffer;
                    break;
                case 'F':
                    return file_put_contents($name, $this->buffer);
                case 'S':
                    return $this->buffer;
                default:
                    $this->Error('Incorrect output destination: '.$dest);
            }
            return '';
        }

        protected function _checkoutput() {
            if (PHP_SAPI !== 'cli') {
                if (headers_sent($file, $line))
                    $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
            }
        }

        protected function _getpagesize($size) {
            if (is_string($size)) {
                $a = strtolower($size);
                if (!isset($this->StdPageSizes[$a])) $this->Error('Unknown page size: '.$size);
                return $this->StdPageSizes[$a];
            } else return array($size[0]*$this->k, $size[1]*$this->k);
        }

        protected function _beginpage($orientation, $size, $rotation) {
            $this->page++;
            $this->pages[$this->page] = '';
            $this->state = 2;
            $this->x = $this->lMargin;
            $this->y = $this->tMargin;
            $this->FontFamily = '';

            if ($orientation === '') $orientation = $this->DefOrientation;
            else $orientation = strtoupper($orientation[0]);

            if ($size === '') $size = $this->DefPageSize;
            else $size = $this->_getpagesize($size);

            if ($orientation !== $this->CurOrientation || $size[0] !== $this->CurPageSize[0] || $size[1] !== $this->CurPageSize[1]) {
                if ($orientation === 'P') {
                    $this->w = $size[0];
                    $this->h = $size[1];
                } else {
                    $this->w = $size[1];
                    $this->h = $size[0];
                }
                $this->wPt = $this->w * $this->k;
                $this->hPt = $this->h * $this->k;
                $this->PageBreakTrigger = $this->h - $this->bMargin;
                $this->CurOrientation = $orientation;
                $this->CurPageSize = $size;
            }

            $this->PageInfo[$this->page] = array('w'=>$this->wPt, 'h'=>$this->hPt);
        }

        protected function _endpage() {
            $this->state = 1;
        }

        protected function _escape($s) {
            return str_replace(array('\\', '(', ')', "\r"), array('\\\\', '\\(', '\\)', ''), $s);
        }

        protected function _dounderline($x, $y, $txt) {
            $up = $this->CurrentFont['up'];
            $ut = $this->CurrentFont['ut'];
            $w = $this->GetStringWidth($txt) + $this->ws * substr_count($txt, ' ');
            return sprintf('%.2F %.2F %.2F %.2F re f', $x*$this->k, ($this->h-($y-$up/1000*$this->FontSize))*$this->k, $w*$this->k, -$ut/1000*$this->FontSizePt);
        }

        protected function _out($s) {
            if ($this->state === 2) $this->pages[$this->page] .= $s."\n";
            else $this->buffer .= $s."\n";
        }

        protected function _enddoc() {
            $this->state = 3;
            $nb = $this->page;

            for ($n = 1; $n <= $nb; $n++) {
                $this->_newobj();
                $this->_out('<</Type /Page');
                $this->_out('/Parent 1 0 R');
                $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->PageInfo[$n]['w'], $this->PageInfo[$n]['h']));
                $this->_out('/Resources 2 0 R');
                $this->_out('/Contents '.($this->n+1).' 0 R>>');
                $this->_out('endobj');

                $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
                $this->_newobj();
                $this->_out('<</Length '.strlen($p));
                if ($this->compress) $this->_out('/Filter /FlateDecode');
                $this->_out('>>');
                $this->_putstream($p);
                $this->_out('endobj');
            }

            $this->offsets[1] = strlen($this->buffer);
            $this->_out('1 0 R');
            $this->_out('<</Type /Pages');
            $kids = '/Kids [';
            for ($i = 0; $i < $nb; $i++) $kids .= (3 + 2*$i).' 0 R ';
            $this->_out($kids.']');
            $this->_out('/Count '.$nb);
            $this->_out('>>');
            $this->_out('endobj');

            $this->_putresources();

            $this->_newobj();
            $this->_out('<</Type /Catalog');
            $this->_out('/Pages 1 0 R');
            $this->_out('>>');
            $this->_out('endobj');

            $o = strlen($this->buffer);
            $this->_out('xref');
            $this->_out('0 '.($this->n+1));
            $this->_out('0000000000 65535 f ');
            for ($i = 1; $i <= $this->n; $i++)
                $this->_out(sprintf('%010d 00000 n ', $this->offsets[$i]));

            $this->_out('trailer');
            $this->_out('<</Size '.($this->n+1));
            $this->_out('/Root '.$this->n.' 0 R');
            $this->_out('/Info 3 0 R');
            $this->_out('>>');
            $this->_out('startxref');
            $this->_out($o);
            $this->_out('%%EOF');
        }

        protected function _newobj() {
            $this->n++;
            $this->offsets[$this->n] = strlen($this->buffer);
            $this->_out($this->n.' 0 obj');
        }

        protected function _putstream($s) {
            $this->_out('stream');
            $this->_out($s);
            $this->_out('endstream');
        }

        protected function _putresources() {
            $this->_putfonts();
            $this->offsets[2] = strlen($this->buffer);
            $this->_out('2 0 R');
            $this->_out('<<');
            $this->_putresourcedict();
            $this->_out('>>');
            $this->_out('endobj');
        }

        protected function _putfonts() {
            foreach ($this->fonts as $k => $font) {
                $this->_newobj();
                $this->fonts[$k]['n'] = $this->n;
                $this->_out('<</Type /Font');
                $this->_out('/Subtype /Type1');
                $this->_out('/BaseFont /'.$font['name']);
                $this->_out('/Encoding /WinAnsiEncoding');
                $this->_out('>>');
                $this->_out('endobj');
            }
        }

        protected function _putresourcedict() {
            $this->_out('/ProcSet [/PDF /Text]');
            $this->_out('/Font <<');
            foreach ($this->fonts as $font)
                $this->_out('/F'.$font['i'].' '.$font['n'].' 0 R');
            $this->_out('>>');
        }

        public function AcceptPageBreak() {
            return $this->AutoPageBreak;
        }
    }
}
