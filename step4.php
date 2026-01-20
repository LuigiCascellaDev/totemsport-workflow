<?php
    $idappuntamento = isset($_GET['idappuntamento']) ? intval($_GET['idappuntamento']) : 0;
    $app = AppointmentHelper::get_app_data($idappuntamento);
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0" style="color:#FFF">Informazioni Aggiuntive</h4>
        </div>
        <div class="card-body">

            <form id="step4-form" method="post">

                <?php if ($app['form_id'] == 5 || $app['form_id'] == 6): ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Il campione delle urine deve prelevarlo da noi?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="urine_radio" id="urine_yes" value="si"
                                required>
                            <label class="form-check-label" for="urine_yes">
                                Sì
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="urine_radio" id="urine_no" value="no"
                                required>
                            <label class="form-check-label" for="urine_no">
                                No
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="mb-4">
                    <div class="form-check">

                        <input class="form-check-input" type="checkbox" disabled <?php echo ($app['pagato'] ? 'checked' : ''); ?> id="payment_confirmed" name="payment_confirmed">

                        <label class="form-check-label fw-bold" for="payment_confirmed">
                            Pagamento effettuato
                        </label>
                    </div>
                </div>

                <div class="text-end">
                    <a type="submit" id="conferma_last" class="btn btn-primary"
                        href="totem/?step=5&idappuntamento=<?php echo $idappuntamento; ?>">
                        Conferma
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

</div>
