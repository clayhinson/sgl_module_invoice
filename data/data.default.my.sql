INSERT INTO module VALUES ({SGL_NEXT_ID}, 1, 'invoice', 'Invoice Manager', 'import and search Garick invoices', '', '48/module_default.png', 'Clay Hinson', NULL, 'NULL', 'NULL');

--
-- "Invoice Document" content type
--

-- insert content type
INSERT INTO `content_type` VALUES ({SGL_NEXT_ID}, 'Invoice Document');

-- get content type id
SELECT @contentTypeIdInvoiceDocument := content_type_id FROM `content_type` WHERE name = 'Invoice Document';

-- insert attributes
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 7, @contentTypeIdInvoiceDocument, 'pdf', 'PDF', '', '');
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 2, @contentTypeIdInvoiceDocument, 'ocrText', 'Raw XML', '', '');




--
-- "Garick Invoice" content type
--

-- insert content type
INSERT INTO `content_type` VALUES ({SGL_NEXT_ID}, 'Garick Invoice');

-- get content type id
SELECT @contentTypeIdGarickInvoice := content_type_id FROM `content_type` WHERE name = 'Garick Invoice';

-- insert attributes
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 1, @contentTypeIdGarickInvoice, 'invoiceNum', 'Invoice #', '', '');
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 1, @contentTypeIdGarickInvoice, 'vendorNum', 'Vendor #', '', '');
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 1, @contentTypeIdGarickInvoice, 'vendorName', 'Vendor', '', '');
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 1, @contentTypeIdGarickInvoice, 'enteredBy', 'Entered By', '', '');
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 1, @contentTypeIdGarickInvoice, 'enteredByUserId', 'User ID', '', '');
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 1, @contentTypeIdGarickInvoice, 'enteredByEmail', 'Email', '', '');
INSERT INTO `attribute` VALUES ({SGL_NEXT_ID}, 1, @contentTypeIdGarickInvoice, 'amount', 'Amount', '', '');
