<?php

class HtmlHelper
{
    public static function show_html_anamnesi($form, $entry)
    {
        if (empty($form) || empty($form['fields']) || empty($entry)) {
            return '';
        }
        $html = '<h3></h3>';
        $html .= '<table class="totemsport-anamnesi-table" style="border-collapse:collapse;width:100%;">';

        foreach ($form['fields'] as $field) {
            if (empty($field->label)) continue;
            if ($field->type === 'html' || $field->type === 'page') continue;

            $fid = $field->id;
            $label = esc_html($field->label);
            $value = '';

            // Gestione sub-input
            if (isset($field->inputs) && is_array($field->inputs)) {
                $parts = [];
                foreach ($field->inputs as $input) {
                    $sub_id = $input['id'];
                    if (!empty($entry[$sub_id])) $parts[] = $entry[$sub_id];
                }
                $value = implode(' ', $parts);
            } else {
                $value = isset($entry[$fid]) ? $entry[$fid] : '';
            }

            if (is_array($value)) $value = implode(', ', $value);
            $value = esc_html($value);

            // Firma
            if ($field->type === "signature") {
                $signature_url = gf_signature()->get_signature_url($value);
                $html .= "
                <tr>
                    <td data-label='{$label}' style='border:1px solid #ccc;padding:6px;width:30%;font-weight:bold'>{$label}</td>
                    <td style='border:1px solid #ccc;padding:6px'>
                        <img src='" . esc_url($signature_url) . "' alt='Firma' style='max-width:300px;display:block;border:1px solid #ddd;padding:6px;background:#fff;' />
                    </td>
                </tr>";
            } 
            // Data
            else if ($field->type === "date") {
                $value = date('d/m/Y', strtotime($value));
                $html .= "<tr>
                    <td data-label='{$label}' style='border:1px solid #ccc;padding:6px;width:30%;font-weight:bold'>{$label}</td>
                    <td style='border:1px solid #ccc;padding:6px'>{$value}</td>
                </tr>";
            } 
            // Consenso
            else if ($field->type === "consent") {
                $value = "<input type='checkbox' disabled " . ($value ? "checked" : "") . " />";
                $html .= "<tr>
                    <td data-label='{$label}' style='border:1px solid #ccc;padding:6px;width:30%;font-weight:bold'>{$label}</td>
                    <td style='border:1px solid #ccc;padding:6px'>{$value}</td>
                </tr>";
            } 
            // Altri campi
            else {
                if ($value) {
                    $html .= "<tr>
                        <td data-label='{$label}' style='border:1px solid #ccc;padding:6px;width:30%;font-weight:bold'>{$label}</td>
                        <td style='border:1px solid #ccc;padding:6px'>{$value}</td>
                    </tr>";
                }
            }
        }

        $html .= '</table>';
        return $html;
    }

    public static function format_app_data_as_table($dati)
    {
        if (empty($dati) || !is_array($dati)) return '';

        $is_guest = !empty($dati['is_guest']);
        $nome     = $is_guest ? ($dati['first_name'] ?? '-') : ($dati['nome'] ?? '-');
        $cognome  = $is_guest ? ($dati['last_name'] ?? '-')  : ($dati['cognome'] ?? '-');
        $cf       = $dati['cf'] ?? '-';
        $data     = $dati['data'] ?? '';
        $ora      = $dati['ora'] ?? '';

        $html  = '<div class="ts-app-details-vertical">';
        $html .= '<div class="ts-app-row"><div class="ts-app-label">Nome <span class="dashicons dashicons-admin-users"></div><div class="ts-app-value">' . esc_html($nome) . '</div></div>';
        $html .= '<div class="ts-app-row"><div class="ts-app-label">Cognome <span class="dashicons dashicons-admin-users"></span></div><div class="ts-app-value">' . esc_html($cognome) . '</div></div>';
        $html .= '<div class="ts-app-row"><div class="ts-app-label">Codice Fiscale <span class="dashicons dashicons-id"></span></span></div><div class="ts-app-value">' . esc_html($cf) . '</div></div>';
        $html .= '<div class="ts-app-row"><div class="ts-app-label">Data Appuntamento <span class="dashicons dashicons-calendar-alt"></span></span></div><div class="ts-app-value">' . esc_html($data) . '</div></div>';
        $html .= '<div class="ts-app-row"><div class="ts-app-label">Ora Appuntamento <span class="dashicons dashicons-clock"></span></div><div class="ts-app-value">' . esc_html($ora) . '</div></div>';
        $html .= '<div class="ts-app-row"><div class="ts-app-label">Appuntamento per minorenne? <span class="dashicons dashicons-heart"></span></div><div class="ts-app-value ts-app-minor-radio">'
            . '<label style="margin-right:12px;"><input type="radio" name="is_minor" value="si"> Si</label>'
            . '<label><input type="radio" name="is_minor" value="no"> No</label>'
            . '</div></div>';
        $html .= '</div>';
        return $html;
    }

}
