<?php

/*
 * Funciones sobre el campo "motivo" de CuentaCorriente.
 * Muchas veces he utilizado este campo para detallar distintos movimientos,
 * pagos, deudas, notas de credito, notas de debito, correcciones.
 * Aunque dicha descripción es bastante uniforme, no he logrado 
 * una nomenclatura estable.
 * El motivo ha ido cambiando de acuerdo 
 * al momento de desarrollo de la parte contable
    // Formatos posibles para "motivo": 
    //      "2015 nivel 2 CU6", 
    //      "no definido en migracion"
    //      "2017, MAT, mes 3, pago 1"
    //      "2015, MAT profesorado nivel 1"
    //      "2017, CU9 profesorado nivel 3, corrección valor"
    //      "2017, MAT profesorado nivel 1, corrección desde precio del alumno"
    //      "2018 alumno_cancelado_del_curso sedes_cursosxanio_id=159"
    //      "2017, CU5"
    //      "CORRECCION"
    //      "MODIFICACION"
    //      "2015,profesorado nivel 4 CU6"
    //      "2014,tecnicatura numero 3 MAT"
 * A partir de la implementación de Agosto de 2018,
 * "motivo" contendrá AÑO, profesorado en minúsculas, ITEM, "corrección", otra extra
 * 
 */

/**
 * 
 *
 * @author mauricio
 */
class MotivoCuentaCorriente 
{
    
    private $_aniosPosibles = array( '2013','2014','2015','2016','2017','2018', '2019' );
    private $_EVAsPosibles = array( 'MAT','CU1','CU2','CU3','CU4','CU5','CU6','CU7','CU8','CU9','DEX' );
      
    // cursos que venían en las descripciones antes de que existiese la tabla relacional
    private $_cursosPosibles = array( 'nivel 1', 'nivel 2', 'nivel 3', 'nivel 4' );
    
    
    public function TESTgetIdentificadores()
    {
        $pruebas = array(
                        '2018, MAT pago 1 de 2',
                        '2018, CU3 pago 1 del nivel 2',
                        '2018, CU3 pago 1 del profesorado nivel 2',
                        '2018, CU3 pago 1 del profesorado   nivel 2',
                        '2018, CU3 pago 1 del profesorado 2',
                        'CU3 pago 1 del 2018,  profesorado 2',
                        'CU3 pago 1 del profesorado 2 2018',
                        '2018, CU5 pago 1 de la Tecnicatura de Embarazadas 2',
                        );
        foreach($pruebas as $texto ){
            ver( $this->getIdentificadores( $texto ), '$texto: '.$texto );
        }
    }
    
    /*
     * Dado un string, devuelve valores respecto a lo que se trata
     * 
     * OUTPUT
     *  array(  'anio'=>???, 
     *          'clasificador_valor'=>???, 
     *          'ev_abreviatura'=>??? 
     *          ); 

     */
    public function getIdentificadores( $descripcion )
    {
        $anio = $this->_getIdentificadorAnio($descripcion);
        $abrev = $this->_getIdentificadorEVAbreviatura($descripcion);
        $nombre = ( strpos(strtolower($descripcion), 'profesorado')!==false )? 'profesorado' :
                    ( ( strpos(strtolower($descripcion), 'tecnicatura')!==false )? 'tecnicatura' : 
                        ( (strpos(strtolower($descripcion), 'nivel')!==false)? 'profesorado' : null ) );
        $cNom   = ( $nombre==null)? null : (( $nombre=='profesorado')? 'nivel' : 'numero' );
        $cValor = $this->_getIdentificadorNumeroNivelDelCurso( $descripcion );
       
        $resultado = array(
                                'anio'                  => $anio,
                                'ev_abreviatura'        => $abrev,
                                'nombre_computacional'  => $nombre,
                                'clasificador_nombre'   => $cNom,
                                'clasificador_valor'    => $cValor,
                            );
        
        return $resultado;
    }
    
    private function _getIdentificadorAnio($descripcion)
    {
        foreach( $this->_aniosPosibles as $anio ){
            if( $this->_existeEsteTexto( $anio, $descripcion ) ){
                return $anio;
            } 
        }
        return null;
    }
    
    private function _getIdentificadorEVAbreviatura($descripcion)
    {
        foreach( $this->_EVAsPosibles as $abreviatura ){
            if( $this->_existeEsteTexto( $abreviatura, $descripcion ) ){
                return $abreviatura;
            } 
        }
        return null;
    }
    
    private function _getIdentificadorNumeroNivelDelCurso( $descripcion )
    {
        foreach( $this->_cursosPosibles as $nivel ){
            if( $this->_existeEsteTexto( $nivel, $descripcion ) ){
                return substr( $nivel, -1 );
            } 
        }
        return null;
    }
    
    private function _existeEsteTexto( $texto, $descripcion )
    {
        if( strpos($descripcion, $texto) !== false ){
            return true;
        }
        return false;
    }
    
}
