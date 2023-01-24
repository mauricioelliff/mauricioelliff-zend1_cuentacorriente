<?php

/*
 * Busca elementos valuados donde 
 * sedes_cursosxanio_alumnos_id  y elementosvaluados_sedes_cursosxanio_id
 * NO apuntan al mismo scxa_id.
 */
require_once 'admin/logicalmodels/Inconsistencia.php';

class InconsistenciaElementosValuados extends Inconsistencia
{
    private $_mensaje = 'Error en InconsistenciaInconsistenciaElementosValuados.php';
    
    public function hayError()
    {
        // sql desde consultas_utiles.sql
        $sql = 'SELECT eva.id AS eva_id, eva.sedes_cursosxanio_alumnos_id AS eva_scxaa_id,
                    eva.elementosvaluados_sedes_cursosxanio_id AS eva_evscxa_id,
                    cursadas.alumnos_id, cursadas.sedes_id,
                    cursadas.anio, cursadas.nombre_humano, 
                    ev.ev_abreviatura,
                    cursadas.scxa_id as cursada_scxa_id,
                    ev.sedes_cursosxanio_id as ev_scxa_id,
                    cursadas.nombre_sede_centro AS sede_alumno,
                    cursadas_ev.nombre_sede_centro AS sede_ev_incorrecto,
                    -- cursadas_ev.nombre_humano AS curso_ev_incorrecto,
                    ev_correcto.evscxa_id AS evscxa_id_correcto,
                    cursadas_ev2.sedes_cursosxanio_id scxa_id_correcto

                FROM `yoga_elementosvaluados_alumnos` AS eva 
                -- con datos de ev
                LEFT JOIN view_elementosvaluados_por_sedes_cursos_y_planes AS ev
                    ON ev.evscxa_id = eva.elementosvaluados_sedes_cursosxanio_id
                -- con datos de las cursadas de alumnos
                LEFT JOIN view_alumnos_por_sedes_cursos_y_planes AS cursadas
                    ON cursadas.scxaa_id = eva.sedes_cursosxanio_alumnos_id
                -- con datos de las cursadas buscando según la referencia del ev
                LEFT JOIN view_sedes_cursos_y_planes AS cursadas_ev
                    ON cursadas_ev.scxa_id = ev.sedes_cursosxanio_id
                -- busco el evscxa_id correcto
                LEFT JOIN view_elementosvaluados_por_sedes_cursos_y_planes AS ev_correcto
                    ON ev_correcto.ev_abreviatura = ev.ev_abreviatura 
                        AND ev_correcto.sedes_cursosxanio_id = cursadas.sedes_cursosxanio_id
                -- verificador de que el ev sería igual que la sede del alumno
                LEFT JOIN view_sedes_cursos_y_planes AS cursadas_ev2
                    ON cursadas_ev2.scxa_id = ev_correcto.sedes_cursosxanio_id

                WHERE cursadas.scxa_id <> ev.sedes_cursosxanio_id 
                ;';
        
        $resultado = $this->_Query->ejecutarQuery($sql);
        if( !is_array($resultado) ){ // || !is_array($resultado) ){
            return ( $this->_mensaje.': El query no funciona.' );
        }
        if( count($resultado) > 0 ){
            return ( $this->_mensaje.': La suma no da cero.' );
        }
        return false; // Sin inconsistencias.
    }
}
