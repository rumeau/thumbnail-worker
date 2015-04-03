<?php
/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 030 30 03 2015
 * Time: 22:57
 */

/**
 * Format values in a query string
 */
if (!function_exists('query_string')) {
    /**
     * @param array $parameters
     * @return string
     */
    function query_string($parameters = [])
    {
        if (empty($parameters)) {
            return '';
        }

        $newParameters = [];
        foreach ($parameters as $key => $val) {
            $newParameters[] = rawurldecode("$key=$val");
        }

        return implode('&amp;', $newParameters);
    }
}

/**
 * Get the first name of a display name
 */
if (!function_exists('first_name')) {
    /**
     * @param null $name
     * @return string
     */
    function first_name($name = null) {
        if ($name === null) {
            $name = \Auth::user()->name;
        }

        $firstName = explode(' ', $name);

        if (isset($firstName[0])) {
            return $firstName[0];
        }

        return '';
    }
}

/**
 * Get a sort link URL
 */
if (!function_exists('sort_link')) {

    function sort_link(\Illuminate\Pagination\AbstractPaginator $paginator, $property, $label = null) {
        /**
         * @var string $by
         * @var string $order
         */
        $order    = \Input::query('order', 'ASC');
        $order_by = \Input::query('order_by', null);
        $q        = \Input::query('q', '');

        if (null === $label) {
            $label = ucwords($property);
        }

        if ($order_by == $property) {
            $order = $order === 'DESC' ? 'ASC' : 'DESC';
        }
        $order_by = $property;

        $order = strtoupper($order);

        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'ASC';
        }

        // Add parameters to the paginator
        $paginator->appends(compact('order', 'order_by', 'q'));
        $url   = $paginator->url($paginator->currentPage());
        // Clean default parameters
        $paginator->appends(['order' => '', 'order_by' => '', 'q' => '']);

        return '<a href="' . $url . '" title="' . $label . '">' . $label .'</a>';
    }
}

/**
 * Checks to see if user is logged in
 */
if (!function_exists('is_logged')) {
    /**
     * @return bool
     */
    function is_logged() {
        return \Auth::check();
    }
}

/**
 * Get the current user object
 */
if (!function_exists('user')) {
    /**
     * @return \App\Models\User|null
     */
    function user() {
        return \Auth::user();
    }
}

/**
 * Returns a formatted pretty date from a commont format
 */
if (!function_exists('pretty_date')) {
    /**
     * @param \Carbon\Carbon $date
     * @param bool $useTimeAgo
     * @return string
     */
    function pretty_date($date, $useTimeAgo = false) {
        $format = 'd-m-Y H:i:s';

        if (!$date instanceof \Carbon\Carbon) {
            trigger_error('First parameter must be a Carbon instance');
        }

        if ($useTimeAgo) {
            return '<time data-toggle="timeago" datetime="' . $date->format('c') . '">' . $date->format($format) . '</time>';
        }

        return $date->format($format);
    }
}