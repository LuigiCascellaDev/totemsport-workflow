<?php

class SearchHelper
{
    public static function get_confirmed_meta($id_conf)
    {
        return [
            'urine'  => get_post_meta($id_conf, 'urine', true),
            'minore' => get_post_meta($id_conf, 'minore', true),
        ];
    }

    public static function search_from_cf($term)
    {
        $results = [];
        $giorno = date('Ymd');

        if (strlen($term) >= 3) {

            // Cerca utenti registrati per codice fiscale
            $users = get_users([
                'meta_key'     => 'billing_codicefiscale',
                'meta_value'   => $term,
                'meta_compare' => 'LIKE',
                'number'       => 10,
                'fields'       => ['ID', 'display_name'],
            ]);

            if (!empty($users)) {
                foreach ($users as $user) {

                    // Verifica che l'utente abbia un appuntamento oggi
                    $args = [
                        'post_type'      => 'wc_appointment',
                        'posts_per_page' => -1,
                        'meta_query'     => [
                            [
                                'key'     => '_appointment_start',
                                'value'   => $giorno,
                                'compare' => 'REGEXP',
                                'type'    => 'NUMERIC'
                            ],
                            [
                                'key'     => '_appointment_customer_id',
                                'value'   => $user->ID,
                                'compare' => '='
                            ]
                        ]
                    ];

                    $appuntamenti = new WP_Query($args);

                    if ($appuntamenti->have_posts()) {

                        // Salta se già confermato
                        $args_conf = [
                            'post_type'      => 'appunt_conf',
                            'posts_per_page' => 1,
                            'meta_query'     => [
                                [
                                    'key'     => 'appointment_id',
                                    'value'   => $appuntamenti->get_posts()[0]->ID,
                                    'compare' => '='
                                ]
                            ]
                        ];

                        $appuntamenti_conf = new WP_Query($args_conf);
                        if ($appuntamenti_conf->have_posts()) {
                            continue;
                        }

                        $cf = get_user_meta($user->ID, 'billing_codicefiscale', true);
                        $results[] = [
                            'label' => $cf . ' - ' . $user->display_name,
                            'value' => $appuntamenti->get_posts()[0]->ID,
                        ];
                    }
                }
            }

            // Se non trova utenti registrati, include anche ospiti
            if (empty($results)) {
                $results = self::get_all_appointments($term);
            }
        }

        return $results;
    }

    public static function get_all_appointments($term)
    {
        global $wpdb;
        $results = [];
        $giorno = date('Ymd');

        $args = [
            'post_type'      => 'wc_appointment',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_appointment_start',
                    'value'   => $giorno,
                    'compare' => 'REGEXP',
                    'type'    => 'NUMERIC'
                ]
            ]
        ];

        $appuntamenti = new WP_Query($args);

        if ($appuntamenti->have_posts()) {
            foreach ($appuntamenti->get_posts() as $appuntamento) {

                $userId = get_post_meta($appuntamento->ID, '_appointment_customer_id', true);

                if ($userId) {
                    // Utente registrato
                    $cf   = get_user_meta($userId, 'billing_codicefiscale', true);
                    $user = get_user_by('id', $userId);
                    $label = $cf . ' - ' . $user->display_name;

                } else {
                    // Ospite tramite ordine
                    $order_item_id = get_post_meta($appuntamento->ID, '_appointment_order_item_id', true);

                    $order_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
                        $order_item_id
                    ));

                    $guest_first_name = get_post_meta($order_id, '_billing_first_name', true);
                    $guest_last_name  = get_post_meta($order_id, '_billing_last_name', true);
                    $guest_cf         = get_post_meta($order_id, 'billing_codicefiscale', true);

                    $label = ($guest_cf ? $guest_cf . ' - ' : '') . trim($guest_first_name . ' ' . $guest_last_name);
                }

                $results[] = [
                    'label' => $label,
                    'value' => $appuntamento->ID,
                ];
            }
        }

        return $results;
    }
}
