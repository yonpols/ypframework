var app = null;

    $(function() {
        app = new Aplicacion();
    });

    function Aplicacion()
    {
        this.ypf = {
            urlTo: function(action, controller, params)
            {
                var url = app.basePath+"/"+controller+"/"+action;
                if (params)
                    url = url + "?" + $.param(params);

                return url;
            },

            get: function(url, params)
            {
                var result = { error: false, data: null }

                $.ajax({
                    type: 'POST',
                    'url': url,
                    async: false,
                    data: params,
                    success: function (data, textStatus, jqXHR)
                    {
                        result.data = data;
                        if (typeof data.error == "string")
                            result.error = data.error;

                        if (typeof data.notice == "string")
                            result.notice = data.notice;
                    },
                    error: function (jqXHR, textStatus, errorThrown) { result.error = textStatus }
                });

                return result;
            },

            remoteAction: function(action, controller, params, callback)
            {
                var r = app.ypf.get(app.ypf.urlTo(action+'.json', controller), params);

                if (r.error)
                    app.error(r.error);
                else if (r.notice)
                    app.mensaje(r.notice);
                else if (callback)
                    callback(r.data);
            },

            action: function(action, controller, params)
            {
                var r = app.ypf.get(app.ypf.urlTo(action, controller), params);

                if (r.error)
                    app.error(r.error);
                else if (r.notice)
                    app.mensaje(r.notice);

                $('body').html(r.data);
            }
        }

        this.gui = {
            showDlg: function(container, html, title)
            {
                if (typeof title == "undefined")
                    title = $(container).attr('title');

                $(container).html(html);
                $(container).dialog({ width: 500, modal: true, 'title': title });
            },

            hideDlg: function(container)
            {
                $(container).dialog('destroy');
            }
        }

        this.validarValor = function(control, valorError)
        {
            if (($(control).size() > 0) && ($(control).val() == valorError))
            {
                $(control).addClass('campoError');
                return false;
            } else
            {
                $(control).removeClass('campoError');
                return true;
            }
        }

        this.validarEntero = function(control)
        {
            var reg = /^[0-9]+$/;

            if (($(control).size() > 0) && !reg.test($(control).val()) && ($(control).val()!=''))
            {
                $(control).addClass('campoError');
                return false;
            } else
            {
                $(control).removeClass('campoError');
                return true;
            }
        }

        this.validarNumero = function(control)
        {
            var reg = /^[0-9]+(\.[0-9]+)?$/;

            if (($(control).size() > 0) && !reg.test($(control).val()) && ($(control).val()!=''))
            {
                $(control).addClass('campoError');
                return false;
            } else
            {
                $(control).removeClass('campoError');
                return true;
            }
        }

        this.validarFecha = function(control)
        {
            var reg = /^[0-3]?[0-9]\/[0-1]?[0-9]\/[0-9]{4}$/;

            if (($(control).size() > 0) && !reg.test($(control).val()))
            {
                $(control).addClass('campoError');
                return false;
            } else
            {
                $(control).removeClass('campoError');
                return true;
            }
        }

        this.validarEmail = function (control)
        {
            var reg = /^[a-zA-Z][\w\.-]*[a-zA-Z0-9]@[a-zA-Z0-9][\w\.-]*[a-zA-Z0-9]\.[a-zA-Z][a-zA-Z\.]*[a-zA-Z]$/;

            if (!reg.test($(control).val()))
            {
                $(control).addClass('campoError');
                return false;
            } else
            {
                $(control).removeClass('campoError');
                return true;
            }
        }

        this.validarNombreUsuario = function (control)
        {
            var reg = /^[a-zA-Z0-9\.]{3}[a-zA-Z0-9\.$]+/;

            if (!reg.test($(control).val()))
            {
                $(control).addClass('campoError');
                return false;
            } else
            {
                $(control).removeClass('campoError');
                return true;
            }
        }

        this.validarClaves = function (contUsuario, contClave, contClave2)
        {
            var res = passwordStrength($(contClave).val(),
                    $(contUsuario).val());

            if (res < 1)
            {
                app.error("La clave no tiene la seguridad necesaria");
                $(contClave).addClass('campoError');
                $(contClave2).addClass('campoError');
                return false;
            } else if ($(contClave).val() != $(contClave2).val())
            {
                app.error("Las claves no coinciden");
                $(contClave).addClass('campoError');
                $(contClave2).addClass('campoError');
                return false;
            } else
            {
                $(contClave).removeClass('campoError');
                $(contClave2).removeClass('campoError');
                return true;
            }
        }


        this.mensaje = function(mensaje)
        {
            $("div#mensaje span.texto").html(mensaje);
            $("div#mensaje").fadeIn(600, function() {
                window.setTimeout(function() {
                    $("div#mensaje").hide("drop", 600);
                }, 2000);
            });
        }

        this.error = function(error)
        {
            $("div#error span.texto").html(error);
            $("div#error").fadeIn(600, function() {
                window.setTimeout(function() {
                    $("div#error").hide("drop", 600);
                }, 2000);
            });
        }
    }



