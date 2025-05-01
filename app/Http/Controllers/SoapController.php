<?php

namespace App\Http\Controllers;

use App\Services\Soap\AuthSoapService;
use Artisaninweb\SoapWrapper\SoapWrapper;

class SoapController extends Controller
{
    protected $soapWrapper;
    protected $authService;

    public function __construct(SoapWrapper $soapWrapper, AuthSoapService $authService)
    {
        $this->soapWrapper = $soapWrapper;
        $this->authService = $authService;
    }

    public function handle()
    {
        $this->soapWrapper->add('Auth', function ($service) {
            $service
                ->wsdl('http://localhost:8000/soap/auth?wsdl')
                ->trace(true)
                ->classmap([
                    'AuthSoapService' => AuthSoapService::class
                ]);
        });

        return $this->soapWrapper->handle();
    }
} 