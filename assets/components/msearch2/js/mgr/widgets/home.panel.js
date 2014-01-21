mSearch2.panel.Home = function(config) {
	config = config || {};
	Ext.apply(config,{
		border: false
		,baseCls: 'modx-formpanel'
		,items: [{
			html: '<h2>'+_('msearch2')+'</h2>'
			,border: false
			,cls: 'modx-page-header container'
		},{
			xtype: 'modx-tabs'
			,bodyStyle: 'padding: 10px'
			,defaults: { border: false ,autoHeight: true }
			,border: true
			,hideMode: 'offsets'
			,stateful: true
			,stateId: 'msearch2-home-tabpanel'
			,stateEvents: ['tabchange']
			,getState:function() {return {activeTab:this.items.indexOf(this.getActiveTab())};}
			,items: [{
				title: _('mse2_tab_search')
				,items: [{
					html: _('mse2_tab_search_intro')
					,border: false
					,bodyCssClass: 'panel-desc'
					,bodyStyle: 'margin-bottom: 10px'
				},{
					xtype: 'msearch2-grid-search'
					,preventRender: true
				}]
			},{
				title: _('mse2_tab_index')
				,items: [{
					html: _('mse2_tab_index_intro')
					,border: false
					,bodyCssClass: 'panel-desc'
					,bodyStyle: 'margin-bottom: 10px'
				},{
					xtype: 'msearch2-form-index'
					,preventRender: true
				}]
			},{
				title: _('mse2_tab_queries')
				,items: [{
					html: _('mse2_tab_queries_intro')
					,border: false
					,bodyCssClass: 'panel-desc'
					,bodyStyle: 'margin-bottom: 10px'
				},{
					xtype: 'msearch2-grid-queries'
					,preventRender: true
				}]
				/*
				,listeners: {
					activate : {fn: function() {
						Ext.getCmp('msearch2-grid-queries').refresh();
					}, scope: this}
				}
				*/
			},{
				title: _('mse2_tab_aliases')
				,items: [{
					html: _('mse2_tab_aliases_intro')
					,border: false
					,bodyCssClass: 'panel-desc'
					,bodyStyle: 'margin-bottom: 10px'
				},{
					xtype: 'msearch2-grid-aliases'
					,preventRender: true
				}]
			},{
				title: _('mse2_tab_dictionaries')
				,items: [{
					html: _('mse2_tab_dictionaries_intro')
					,border: false
					,bodyCssClass: 'panel-desc'
					,bodyStyle: 'margin-bottom: 10px'
				},{
					xtype: 'msearch2-grid-dictionaries'
					,preventRender: true
				}]
			}]
		}]
	});
	mSearch2.panel.Home.superclass.constructor.call(this,config);
};
Ext.extend(mSearch2.panel.Home,MODx.Panel);
Ext.reg('msearch2-panel-home',mSearch2.panel.Home);
