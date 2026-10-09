<?php
namespace App\Services;

class CertificateDocument extends \setasign\Fpdi\Tcpdf\Fpdi
{
    public function __construct() {parent::__construct();$this->tcpdflink=false;}
}
