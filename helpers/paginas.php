<?php
    function paginas_links($link, $cantidad, $pagina=1, $itemsPorPagina=20)
    {
        if ($itemsPorPagina >= $cantidad)
            return;

        $paginas = floor($cantidad / $itemsPorPagina);
        if ($cantidad % $itemsPorPagina) $paginas++;

        $min = max(1, $pagina - 10);
        $max = min($paginas, $pagina + 10);

        if (strpos($link, '?') !== false)
            $link .= '&';
        else
            $link .= '?';

        if ($min > 1)
            printf('<span class="link_pagina"><a href="%spagina=1">1</a> ...</span>', $link);

        for ($i = $min; $i <= $max; $i++)
            if ($pagina != $i)
                printf('<span class="link_pagina"><a href="%spagina=%d">%d</a></span>', $link, $i, $i);
            else
                printf('<span class="link_pagina">%d</span>', $i);

        if ($max < $paginas)
            printf('<span class="link_pagina">... <a href="%spagina=%d">%d</a></span>', $link, $paginas, $paginas);
    }

    function paginas_listado($modelo, $pagina=1, $itemsPorPagina=20)
    {
        return $modelo->limit(array(($pagina-1)*$itemsPorPagina, $itemsPorPagina));
    }
 ?>
