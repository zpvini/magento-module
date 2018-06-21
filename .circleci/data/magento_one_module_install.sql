INSERT INTO magento.core_config_data (config_id, scope, scope_id, path, value)
VALUES (NULL , 'default', '0', 'dev/template/allow_symlink', '1');

INSERT INTO magento.core_config_data (scope, scope_id, path, value) VALUES
-- module
('default', 0, 'mundipagg_config/general_group/module_status', '1'),
('default', 0, 'mundipagg_config/general_group/logs', '1'),
('default', 0, 'mundipagg_config/general_group/sk_prod', null),
('default', 0, 'mundipagg_config/general_group/pk_prod', null),
('default', 0, 'mundipagg_config/general_group/test_mode', '1'),
('default', 0, 'mundipagg_config/antifraud_group/antifraud_status', '0'),
('default', 0, 'mundipagg_config/boleto_group/boleto_status', '1'),
('default', 0, 'mundipagg_config/boleto_group/boleto_payment_title', 'Boleto'),
('default', 0, 'mundipagg_config/boleto_group/boleto_name', 'boleto'),
('default', 0, 'mundipagg_config/boleto_group/boleto_bank', '001'),
('default', 0, 'mundipagg_config/boleto_group/boleto_due_at', '3'),
('default', 0, 'mundipagg_config/boleto_group/boleto_instructions', 'Pagar até o vencimento.'),
('default', 0, 'mundipagg_config/twocreditcards_group/twocreditcards_status', '1'),
('default', 0, 'mundipagg_config/twocreditcards_group/twocreditcards_payment_title', 'Two credit Cards'),
('default', 0, 'mundipagg_config/creditcard_group/cards_config_status', '1'),
('default', 0, 'mundipagg_config/creditcard_group/creditcard_payment_title', 'Credit card'),
('default', 0, 'mundipagg_config/creditcard_group/invoice_name', 'Magento STG'),
('default', 0, 'mundipagg_config/creditcard_group/operation_type', 'AuthAndCapture'),
('default', 0, 'mundipagg_config/creditcard_group/saved_cards_status', '1'),
('default', 0, 'mundipagg_config/boletocreditcard_group/boleto_cards_config_status', '1'),
('default', 0, 'mundipagg_config/boletocreditcard_group/boleto_creditcard_payment_title', 'Boleto + Creditcard'),
('default', 0, 'mundipagg_config/boletocreditcard_group/boleto_cards_name', 'Boleto'),
('default', 0, 'mundipagg_config/boletocreditcard_group/boleto_cards_bank', '001'),
('default', 0, 'mundipagg_config/boletocreditcard_group/boleto_cards_due_at', '3'),
('default', 0, 'mundipagg_config/boletocreditcard_group/boleto_cards_instructions', 'Pague até o venciomento. (CC)'),
('default', 0, 'mundipagg_config/boletocreditcard_group/boleto_cards_invoice_name', 'Magento STG BoletoCreditCArd'),
('default', 0, 'mundipagg_config/boletocreditcard_group/boleto_cards_operation_type', 'AuthAndCapture'),
('default', 0, 'mundipagg_config/installments_group/default_status', '1'),
('default', 0, 'mundipagg_config/installments_group/default_max_installments', '12'),
('default', 0, 'mundipagg_config/installments_group/default_max_without_interest', '3'),
('default', 0, 'mundipagg_config/installments_group/default_interest', '1'),
('default', 0, 'mundipagg_config/installments_group/default_incremental_interest', '0.5'),
('default', 0, 'mundipagg_config/installments_group/visa_status', '1'),
('default', 0, 'mundipagg_config/installments_group/mastercard_status', '1'),
('default', 0, 'mundipagg_config/installments_group/hipercard_status', '1'),
('default', 0, 'mundipagg_config/installments_group/diners_status', '1'),
('default', 0, 'mundipagg_config/installments_group/amex_status', '1'),
('default', 0, 'mundipagg_config/installments_group/elo_status', '1'),
('default', 0, 'mundipagg_config/multibuyer_group/multibuyer_status ', '1');

UPDATE magento.core_config_data SET value = '1' WHERE path = 'dev/log/active';
UPDATE magento.core_config_data SET value = 'system.log' WHERE path = 'dev/log/file';
UPDATE magento.core_config_data SET value = 'exception.log' WHERE path = 'dev/log/exception_file';

-- creating user



