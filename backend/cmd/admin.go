package cmd

import (
	"fmt"

	"directadmin/pkg/admin"
	"github.com/spf13/cobra"
)

var adminCmd = &cobra.Command{
	Use:     "admin",
	Aliases: []string{"a"},
	Short:   "Print admin username",
	Long:    "Print the admin username",
	Run: func(cmd *cobra.Command, args []string) {
		adminUser := admin.GetAdminUsername()
		fmt.Println(adminUser)
	},
}

var adminBackupCmd = &cobra.Command{
	Use:   "admin-backup",
	Short: "Perform admin-level backups",
	Long:  "Perform admin-level backup operations",
	Run: func(cmd *cobra.Command, args []string) {
		err := admin.PerformBackup()
		if err != nil {
			fmt.Printf("Backup failed: %v\n", err)
			return
		}
		fmt.Println("Admin backup completed successfully")
	},
}