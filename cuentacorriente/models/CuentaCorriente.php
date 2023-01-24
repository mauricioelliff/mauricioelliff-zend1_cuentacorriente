<?php
/**
 * Registra los movimientos de dinero
 * 
 * El motivo ha ido cambiando de acuerdo 
 * al momento de desarrollo de la parte contable
    // Valores posibles para "motivo": 
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
 * A partir de la implementación del concepto de cobertura,
 * será un indicador de la intención de pago, 
 * pues la relación real con los items asociados se dará
 * a traves de la tabla de relacion de ctasctes y elementosValuados
 * 
 * 
 * "motivo" contendrá AÑO, profesorado en minúsculas, ITEM, "corrección", otra extra
 * 
 * 
 */

class CuentaCorriente
{
    private $_id;       
    private $_origen;
    private $_alumnos_id;
    private $_tipo_operacion;       // FM:factura manual, FA:factura automatica, CM:cobro manual, CA:cobro automatico, NC:Nota credito 
    private $_fecha_operacion;      // es la fecha a que corresponde el movimiento de dinero
    private $_monto;
    private $_cobertura;            // Indicador de cuanto se ha saldado este item. Utiliza un valor de igual signo que el monto.
    private $_motivo;               // EV (abreviatura) directamente relacionado, o CURSO, RETIRO, A CUENTA, etc
    private $_comprobante_sede;
    private $_comprobante;
    private $_persona_en_caja;      // quien cobro o manipulo dinero. String.
    private $_observaciones;
    private $_usuario_nombre;       // Es el operador logueado, en principio esta dentro de las variables de session pasadas por el sistema Admin
    private $_fecha_hora_de_sistema;// momento en que se crea la row. Fines de auditoria

    public function __construct( array $valores )
    {
        $this->_id                      = isset( $valores['id'] )                       ? $valores['id'] : null;
        $this->_origen                  = isset( $valores['origen'] )                   ? $valores['origen'] : 'A';
        $this->_alumnos_id              = isset( $valores['alumnos_id'] )               ? $valores['alumnos_id'] : null;       
        $this->_tipo_operacion          = isset( $valores['tipo_operacion'] )           ? $valores['tipo_operacion'] : null;       
        $this->_fecha_operacion         = isset( $valores['fecha_operacion'] )          ? $valores['fecha_operacion'] : null;       
        $this->_monto                   = isset( $valores['monto'] )                    ? $valores['monto'] : null;       
        $this->_cobertura               = isset( $valores['cobertura'] )                ? $valores['cobertura'] : 0;       
        $this->_motivo                  = isset( $valores['motivo'] )                   ? $valores['motivo'] : null;       
        $this->_comprobante_sede        = isset( $valores['comprobante_sede'] )         ? $valores['comprobante_sede'] : null;       
        $this->_comprobante             = isset( $valores['comprobante'] )              ? $valores['comprobante'] : null;       
        $this->_persona_en_caja         = isset( $valores['persona_en_caja'] )          ? $valores['persona_en_caja'] : null;       
        $this->_observaciones           = isset( $valores['observaciones'] )            ? $valores['observaciones'] : null;       
        $this->_usuario_nombre          = isset( $valores['usuario_nombre'] )           ? $valores['usuario_nombre'] : null;       
        $this->_fecha_hora_de_sistema   = isset( $valores['fecha_hora_de_sistema'] )    ? $valores['fecha_hora_de_sistema'] : datetimeMicroseconds(); //date('Y-m-d H:i:s');       
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getOrigen()
    {
        return $this->_origen;
    }

    public function getAlumnosId()
    {
        return $this->_alumnos_id;
    }

    public function getTipoOperacion()
    {
        return $this->_tipo_operacion;
    }

    public function getFechaOperacion()
    {
        return $this->_fecha_operacion;
    }
    public function getAnio()
    {
        return substr( $this->getFechaOperacion(), 0, 4 );
    }

    public function getMonto()
    {
        return $this->_monto;
    }

    public function getCobertura()
    {
        return $this->_cobertura;
    }
    public function setCobertura($cobertura)
    {
        return $this->_cobertura = $cobertura;
    }
    
    public function getSaldo()
    {
        return $this->getMonto() - $this->getCobertura();
    }

    public function getMotivo()
    {
        return $this->_motivo;
    }

    public function getComprobanteSede()
    {
        return $this->_comprobante_sede;
    }

    public function getComprobante()
    {
        return $this->_comprobante;
    }

    public function getPersonaEnCaja()
    {
        return $this->_persona_en_caja;
    }

    public function getObservaciones()
    {
        return $this->_observaciones;
    }

    public function getUsuarioNombre()
    {
        return $this->_usuario_nombre;
    }

    public function getFechaHoraDeSistema()
    {
        return $this->_fecha_hora_de_sistema;
    }



     /*
     * Convierte el objeto en array.
     * Debe hacerse desde la propia clase pues se trata de variables privadas.
     */
    public function convertirEnArray()
    {
        //return get_object_vars($this); //esto lo devuelve con los underscord
        $miIterator=array();
        foreach($this as $key => $value) {
            $key=substr($key,1); //con esto le quito el underscord primero.
            $miIterator[$key]=$value;
        }
        return $miIterator;
    }



}