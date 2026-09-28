addRoute('GET', '/admin', 'AdminController@index');
            
            // 2. Главная страница сайта (корень)
            $r->addRoute('GET', '/', 'NodeController@resolve');
            
            // 3. Динамический маршрут для вложенных страниц (узлов)
            $r->addRoute('GET', '/{path:.+}', 'NodeController@resolve');
        });

        \(routeInfo =\)dispatcher->dispatch(\(httpMethod,\)uri);
        
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                http_response_code(404);
                echo "



# 404 Not Found

Узел не найден в структуре сайта.

";
break;
case Dispatcher::METHOD_NOT_ALLOWED:
http_response_code(405);
echo "

# 405 Method Not Allowed

";
break;
case Dispatcher::FOUND:
$handler =$routeInfo[1];
$vars =$routeInfo[2] ?? [];


            [\(class,\)method] = explode('@', $handler);
            
            echo "



";
echo "**Чистота без компромиссов.**





";
echo "Маршрут найден!



";
echo "Контроллер: **{$class}**



";
echo "Метод: **{$method}**



";
echo "Переменные пути: "; print_r($vars);
echo "

";
break;
}
}
}
