package cmd

import (
	"github.com/spf13/cobra"
)

var rootCmd = &cobra.Command{
	Use:   "directadmin",
	Short: "DirectAdmin Web Control Panel",
	Long:  "DirectAdmin Web Control Panel - A comprehensive web hosting control panel",
	Run: func(cmd *cobra.Command, args []string) {
		if len(args) == 0 {
			cmd.Help()
			return
		}
	},
}

func Execute() error {
	return rootCmd.Execute()
}

func init() {
	// Add all subcommands
	rootCmd.AddCommand(adminCmd)
	rootCmd.AddCommand(adminBackupCmd)
	rootCmd.AddCommand(apiUrlCmd)
	rootCmd.AddCommand(buildCmd)
	rootCmd.AddCommand(configCmd)
	rootCmd.AddCommand(configGetCmd)
	rootCmd.AddCommand(configSetCmd)
	rootCmd.AddCommand(doveadmQuotaCmd)
	rootCmd.AddCommand(infoCmd)
	rootCmd.AddCommand(installCmd)
	rootCmd.AddCommand(licenseCmd)
	rootCmd.AddCommand(licenseSetCmd)
	rootCmd.AddCommand(loginUrlCmd)
	rootCmd.AddCommand(permissionsCmd)
	rootCmd.AddCommand(serverCmd)
	rootCmd.AddCommand(suspendDomainCmd)
	rootCmd.AddCommand(suspendUserCmd)
	rootCmd.AddCommand(taskqCmd)
	rootCmd.AddCommand(unsuspendDomainCmd)
	rootCmd.AddCommand(unsuspendUserCmd)
	rootCmd.AddCommand(updateCmd)
	rootCmd.AddCommand(versionCmd)
	rootCmd.AddCommand(webInstallCmd)
}