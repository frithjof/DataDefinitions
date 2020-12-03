/**
 * Data Definitions.
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2016-2019 w-vision AG (https://www.w-vision.ch)
 * @license    https://github.com/w-vision/DataDefinitions/blob/master/gpl-3.0.txt GNU General Public License version 3 (GPLv3)
 */

pimcore.registerNS('pimcore.plugin.datadefinitions.provider.bmecat');
pimcore.plugin.datadefinitions.provider.bmecat = Class.create(pimcore.plugin.datadefinitions.provider.abstractprovider, {
    getItems: function () {
        var optionStore = Ext.create('Ext.data.Store', {
            fields: ['method'],
            data: [{
                'method': 'articles'
                },
                {
                "method": 'categories'
                }]
        });
        return [{
            xtype: 'combobox',
            name: 'sourcemode',
            fieldLabel: t('data_definitions_bmecat_sourcemode'),
            anchor: '100%',
            store: optionStore,
            valueField: 'method',
            displayField: 'method',
            value: this.data['sourcemode'] ? this.data.sourcemode : ''
        },{
            xtype: 'textfield',
            name: 'classificationStore',
            fieldLabel: t('data_definitions_bmecat_classificationStoreId'),
            anchor: '100%',
            value: this.data['classificationStore'] ? this.data.classificationStore : ''
        },{
            xtype: 'textfield',
            name: 'xPath',
            fieldLabel: t('data_definitions_xml_xpath'),
            anchor: '100%',
            value: this.data['xPath'] ? this.data.xPath : ''
        }, {
            xtype: 'textfield',
            name: 'exampleXPath',
            fieldLabel: t('data_definitions_xml_exampleXPath'),
            anchor: '100%',
            value: this.data['exampleXPath'] ? this.data.exampleXPath : ''
        }, {
            fieldLabel: t('data_definitions_xml_file'),
            name: 'exampleFile',
            cls: 'input_drop_target',
            value: this.data['exampleFile'] ? this.data.exampleFile : '',
            xtype: 'textfield',
            listeners: {
                render: function (el) {
                    new Ext.dd.DropZone(el.getEl(), {
                        reference: this,
                        ddGroup: 'element',
                        getTargetFromEvent: function (e) {
                            return this.getEl();
                        }.bind(el),

                        onNodeOver: function (target, dd, e, data) {
                            data = data.records[0].data;

                            if (data.elementType == 'asset') {
                                return Ext.dd.DropZone.prototype.dropAllowed;
                            }

                            return Ext.dd.DropZone.prototype.dropNotAllowed;
                        },

                        onNodeDrop: function (target, dd, e, data) {
                            data = data.records[0].data;

                            if (data.elementType == 'asset') {
                                this.setValue(data.id);
                                return true;
                            }

                            return false;
                        }.bind(el)
                    });
                }
            }
        }];
    }
});
