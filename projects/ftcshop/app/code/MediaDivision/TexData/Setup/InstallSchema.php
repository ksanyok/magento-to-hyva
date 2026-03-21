<?php
namespace MediaDivision\TexData\Setup;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{

    public function install(\Magento\Framework\Setup\SchemaSetupInterface $setup, \Magento\Framework\Setup\ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        
        
        if (!$installer->tableExists('md_sequence')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable('md_sequence')
            )
                ->addColumn(
                    'id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'nullable' => false,
                        'primary'  => true,
                        'unsigned' => true,
                    ],
                    'ID'
                )
                ->addColumn(
                    'usage',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    255,
                    ['nullable => false'],
                    'Name'
                )->addColumn(
                    'create_date',
                    \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                    null,
                    [],
                    'Creation Date'
                );
            $installer->getConnection()->createTable($table);

        }
        
        $installer->endSetup();
    }
}