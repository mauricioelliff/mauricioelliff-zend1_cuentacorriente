-include Makefile.excepciones_locales

.depend : CVS/Entries
	generar_dependencias_deploy_web_escuela_natha $(EXCEPCIONES_LOCALES)

-include .depend

# Si el archivo .depend no existe el make lo regenera pero no lo incluye
# a tiempo. Por esa razón si las variables no se cargaron se debe reejecutar
# el make desde el principio.

ifdef ORIGEN_RELATIVO

include $(ORIGEN_RELATIVO)/Makefile.rules

-include Makefile.local

else

Makefile.excepciones_locales : ; @true

  ifdef STOP_RECURSION

% : ; @false

  else
% : ; $(MAKE) $@ STOP_RECURSION=1

  endif

endif
