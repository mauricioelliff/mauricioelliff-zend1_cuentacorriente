<?php


/**
 * Description of Cobertura
 *
 * @author mauricio
 */
class Cobertura {
    
    /*
     * Esta función calcula los valores que deberán aplicarse 
     * a un item modificado y al ajuste que nace de modificar dicho item.
     * 
     * Ejemplo de un caso de uso:
     * 
     *   Se cambia el precio de un item. 
     *   con valor de 100$ 
     *   tiene ahora una bonificacinó de $20, 
     *   resultante en un precio final de 80$.
     *   
     *   Si ya tenía una cobertura de $90,            
     *   ¿En cuańto quedará la cobertura de dicha factura?
     *   Es importante entender, que aquí no se modifica el precio.
     *   Los precios o montos principales, nunca se modifican.
     *   La variante del precio o deuda, la genera el ajuste
     *   que deberá crearse.
     *   En el ejemplo, la BD estaba así:
     *   item    monto:-100, cobertura:-90
     *   Ahora:
     *   item    monto:-100, cobertura:-100
     *   ajuste  monto:20,   cobertura:10
     * 
     * 
     * INPUT
     * 
     *      SOBRE LA NOVEDAD
     *          $valorViejo     (siempre positivo)
     *          $valorNuevo     (siempre positivo)
     * 
     *      SOBRE EL ITEM EN LA TABLA DE BD
     *          $itemMonto      ( negativos para débitos, positivos para créditos)
     *          $itemCobertura  ( de 0 a monto )
     *      
     * 
     * 
     * OUTPUT
     *      <int>   valor que refiere a cuanto debe trasladarse al item,
     *              (Este valor se sumará a la cobertura actual para modificarla)
     */
    public function getValorAModificarCobertura(    $valorViejo, 
                                                    $valorNuevo, 
                                                    $itemMonto, 
                                                    $itemCobertura 
                                                )
    {
                            // los valores vienen siempre como positivos
        $diferenciaValores = $valorViejo - $valorNuevo ;

        // MODIFICACION DE LA COBERTURA
        if( $diferenciaValores < 0 ){
            // está debitando, aumentando la deuda del alumno.

            if( $itemMonto < 0 ){
                // en ctacte es un debito. 
                // Esa deuda aumenta, por lo que la cobertura disminuye.
                // (Sin que la cobertura termine > 0 )
                $maxValorModiCobertura = $itemCobertura;
                $valorAModificarCobertura = ( $diferenciaValores < $maxValorModiCobertura )? $maxValorModiCobertura : $diferenciaValores;
                // la dejo en signo +
                $valorAModificarCobertura = -$valorAModificarCobertura;
            }else{
                // en ctacte es un crédito.
                // Ese crédito disminuye su pontecial por lo que la cobertura aumenta.
                // (Sin que la cobertura termine mayor al monto total del crédito inicial).
                $maxValorModiCobertura = $itemMonto - $itemCobertura;
                // se mantiene en signo +
                $valorAModificarCobertura = ( -$diferenciaValores < $maxValorModiCobertura )? -$diferenciaValores : $maxValorModiCobertura;
            }
        }else{
            // está acreditando, disminuyendo la deuda del alumno

            if( $itemMonto < 0 ){
                // en ctacte es un debito. 
                // Esa deuda disminuye, por lo que la cobertura aumenta.
                // Sin que la cobertura termine mayor al monto total del debito inicial.
                $maxValorModiCobertura = $itemMonto - $itemCobertura;
                // devolverá un valor negativo
                $valorAModificarCobertura = ( -$diferenciaValores < $maxValorModiCobertura )? $maxValorModiCobertura : -$diferenciaValores;
            }else{
                // en ctacte es un crédito.
                // Ese crédito aumenta su pontecial por lo que la cobertura disminuye.
                // Sin que la cobertura termine < 0
                $maxValorModiCobertura = $itemCobertura;
                $valorAModificarCobertura = ( $diferenciaValores < $maxValorModiCobertura )? $maxValorModiCobertura : $diferenciaValores;
                // devuelvo un valor negativo para que reste a la cobertura
                $valorAModificarCobertura = -$valorAModificarCobertura;
            }
        }
        // $nuevaCobertura = $itemCobertura + $valorAModificarCobertura;

        return $valorAModificarCobertura;
    }
    
    
}
