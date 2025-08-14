package cmd

import (
	"fmt"

	"admini/pkg/domain"
	"admini/pkg/user"
	"admini/pkg/taskqueue"
	"github.com/spf13/cobra"
)

var suspendDomainCmd = &cobra.Command{
	Use:   "suspend-domain [domain]",
	Short: "Suspend domain",
	Long:  "Suspend a domain",
	Args:  cobra.ExactArgs(1),
	Run: func(cmd *cobra.Command, args []string) {
		err := domain.Suspend(args[0])
		if err != nil {
			fmt.Printf("Failed to suspend domain: %v\n", err)
			return
		}
		fmt.Printf("Domain %s suspended successfully\n", args[0])
	},
}

var unsuspendDomainCmd = &cobra.Command{
	Use:   "unsuspend-domain [domain]",
	Short: "Unsuspend domain",
	Long:  "Unsuspend a domain",
	Args:  cobra.ExactArgs(1),
	Run: func(cmd *cobra.Command, args []string) {
		err := domain.Unsuspend(args[0])
		if err != nil {
			fmt.Printf("Failed to unsuspend domain: %v\n", err)
			return
		}
		fmt.Printf("Domain %s unsuspended successfully\n", args[0])
	},
}

var suspendUserCmd = &cobra.Command{
	Use:   "suspend-user [username]",
	Short: "Suspend user",
	Long:  "Suspend a user account",
	Args:  cobra.ExactArgs(1),
	Run: func(cmd *cobra.Command, args []string) {
		err := user.Suspend(args[0])
		if err != nil {
			fmt.Printf("Failed to suspend user: %v\n", err)
			return
		}
		fmt.Printf("User %s suspended successfully\n", args[0])
	},
}

var unsuspendUserCmd = &cobra.Command{
	Use:   "unsuspend-user [username]",
	Short: "Unsuspend user",
	Long:  "Unsuspend a user account",
	Args:  cobra.ExactArgs(1),
	Run: func(cmd *cobra.Command, args []string) {
		err := user.Unsuspend(args[0])
		if err != nil {
			fmt.Printf("Failed to unsuspend user: %v\n", err)
			return
		}
		fmt.Printf("User %s unsuspended successfully\n", args[0])
	},
}

var taskqCmd = &cobra.Command{
	Use:   "taskq",
	Short: "Run dataskq",
	Long:  "Run the task queue processor (dataskq)",
	Run: func(cmd *cobra.Command, args []string) {
		fmt.Println("Starting task queue processor...")
		err := taskqueue.Run()
		if err != nil {
			fmt.Printf("Task queue failed: %v\n", err)
			return
		}
	},
}