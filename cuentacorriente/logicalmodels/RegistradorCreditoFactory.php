<?php

require_once 'cuentacorriente/logicalmodels/RegistradorDePago.php';
require_once 'cuentacorriente/logicalmodels/RegistradorDeNotaDeCreditoManual.php';


class RegistradorCreditoFactory
{
    public static function getRegistrador( array $datosDelMov, $simular=false )
    {
        if( $datosDelMov['tipo_operacion']=='PAGO_MANUAL' ){
            return new RegistradorDePago( $datosDelMov, $simular );
        }elseif( $datosDelMov['tipo_operacion']=='NOTA_CREDITO_MANUAL' ){
            return new RegistradorDeNotaDeCreditoManual( $datosDelMov, $simular );
            
        /* Esta sin uso.
        }elseif( $datosDelMov['tipo_operacion']=='NOTA_CREDITO_AUTOMATICO' ){
         * Actualmente se resuelve en CuentasCorrientes.php -> _procesarModificacionDeValorEV()
            return new RegistradorDeNotaDeCreditoAutomatica( $datosDelMov, $simular );
         * 
         */
        }else{
            // ERROR
        }
        return false;
    }
}