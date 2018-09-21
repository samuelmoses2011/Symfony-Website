<?php

namespace IIAB\MagnetBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class IIABMagnetBundle extends Bundle {

    public function boot()
    {
        if( !defined( 'MYPICK_CONFIG' ) ){
            define ( 'MYPICK_CONFIG' , $this->container->getParameter('customer_configuration') );
        }
    }
}
