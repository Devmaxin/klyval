<?php
final class View {
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): void {
        extract($data, EXTR_SKIP);
        ob_start();
        require ROOT . "/views/$view.php";
        $content = ob_get_clean();
        require ROOT . "/views/$layout.php";
    }
    /** Page-section include (views/pages/...). Shares the render() data. */
    public static function section(string $path, array $data = []): void {
        extract($data, EXTR_SKIP);
        require ROOT . "/views/$path.php";
    }
    public static function partial(string $name, array $data = []): void {
        extract($data, EXTR_SKIP);
        require ROOT . "/views/components/$name.php";
    }
}
